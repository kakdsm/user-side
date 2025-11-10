<?php
require_once '../php/session_init.php';
include '../php/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please log in to see recommended jobs',
        'code' => 'NOT_LOGGED_IN'
    ]);
    exit();
}

$user_id = $_SESSION['userid'];

try {
    // First check if user has taken the test
    $checkQuery = "SELECT * FROM job_recommendations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $checkStmt = $con->prepare($checkQuery);
    $checkStmt->bind_param("i", $user_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please complete the career assessment first to get personalized recommendations',
            'code' => 'NO_ASSESSMENT'
        ]);
        exit();
    }
    
    $testData = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Get recommended job roles from the test results
    $recommendedRoles = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($testData["job$i"])) {
            $recommendedRoles[] = $testData["job$i"];
        }
    }
    
    if (empty($recommendedRoles)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No career recommendations found in your test results',
            'code' => 'NO_RECOMMENDATIONS'
        ]);
        exit();
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($recommendedRoles) - 1) . '?';
    
    // Modified query with COLLATE to handle collation mismatch
    $query = "
        SELECT 
            jp.*
        FROM jobposting jp
        WHERE jp.poststatus = 'Open'
        AND jp.postjobrole COLLATE utf8mb4_unicode_ci IN ($placeholders)
        ORDER BY 
            CASE jp.postjobrole COLLATE utf8mb4_unicode_ci
    ";
    
    // Add CASE conditions for each recommended role to maintain order
    foreach ($recommendedRoles as $index => $role) {
        $query .= " WHEN ? THEN " . ($index + 1);
    }
    
    $query .= " ELSE " . (count($recommendedRoles) + 1) . " END";
    
    // Prepare the statement
    $stmt = $con->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $con->error);
    }
    
    // Bind parameters: first the roles for IN clause, then again for CASE ordering
    $types = str_repeat('s', count($recommendedRoles) * 2);
    $params = array_merge($recommendedRoles, $recommendedRoles);
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recommendedJobs = [];
    while ($row = $result->fetch_assoc()) {
        // Add match confidence based on position in recommendations
        $roleIndex = array_search($row['postjobrole'], $recommendedRoles);
        $row['match_confidence'] = $roleIndex !== false ? $testData["job" . ($roleIndex + 1) . "_confidence"] : 0;
        $recommendedJobs[] = $row;
    }
    
    $stmt->close();
    
    if (empty($recommendedJobs)) {
        echo json_encode([
            'success' => false, 
            'message' => 'No current job openings match your career recommendations. Please check back later.',
            'code' => 'NO_MATCHING_JOBS'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'jobs' => $recommendedJobs,
            'total' => count($recommendedJobs),
            'message' => 'Found ' . count($recommendedJobs) . ' jobs matching your profile'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Recommended jobs error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'System error: ' . $e->getMessage(),
        'code' => 'SERVER_ERROR'
    ]);
}
?>
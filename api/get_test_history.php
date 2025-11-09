<?php
require_once '../php/session_init.php';

include '../php/database.php';

ob_clean();
header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$userid = $_SESSION['userid'];

// Function to convert probability to match percentage
// Function to convert probability to match percentage (same logic as JavaScript)
function calculateMatchPercentage($probability, $rank) {
    $baseScore = $probability * 100;
    
    $matchPercentage = 0;
    
    if ($rank === 0) {
        $matchPercentage = 60 + ($baseScore * 0.6);
    } else if ($rank === 1) {
        $matchPercentage = 55 + ($baseScore * 0.5);
    } else if ($rank === 2) {
        $matchPercentage = 50 + ($baseScore * 0.45);
    } else if ($rank === 3) {
        $matchPercentage = 45 + ($baseScore * 0.4);
    } else {
        $matchPercentage = 40 + ($baseScore * 0.35);
    }
    
    $matchPercentage = max(40, min(95, $matchPercentage));
    return round($matchPercentage);
}


try {
    // Query for latest test result with all job recommendations
    $latestQuery = "SELECT 
                    job1, job1_confidence,
                    job2, job2_confidence,
                    job3, job3_confidence,
                    job4, job4_confidence,
                    job5, job5_confidence,
                    created_at as test_date,
                    critical_thinking,
                    problem_solving, 
                    communication,
                    teamwork,
                    adaptability
                  FROM job_recommendations 
                  WHERE user_id = ? 
                    AND job1 IS NOT NULL 
                    AND job1 != '' 
                    AND job1 != '0'
                  ORDER BY created_at DESC 
                  LIMIT 1";
    
    $stmt = $con->prepare($latestQuery);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $latestResult = $stmt->get_result();
    $latestTest = $latestResult->fetch_assoc();
    $stmt->close();

    // Query for test history (last 10 tests)
    $historyQuery = "SELECT 
                    job1 as recommended_position, 
                    job1_confidence as match_probability,
                    created_at as test_date
                  FROM job_recommendations 
                  WHERE user_id = ? 
                    AND job1 IS NOT NULL 
                    AND job1 != '' 
                    AND job1 != '0'
                  ORDER BY created_at DESC 
                  LIMIT 10";
    
    $stmt = $con->prepare($historyQuery);
    $stmt->bind_param("i", $userid);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    $testHistory = [];
    
    while ($row = $historyResult->fetch_assoc()) {
        $matchPercentage = calculateMatchPercentage($row['match_probability'], 0);
        
        $testHistory[] = [
            'date' => $row['test_date'],
            'suggested_job' => $row['recommended_position'],
            'match_score' => $matchPercentage . '%'
        ];
    }
    $stmt->close();

    ob_clean();
    
    // Prepare response
    $response = [
        'success' => true,
        'test_history' => $testHistory,
        'count' => count($testHistory)
    ];

    // Add latest test data if available
    if ($latestTest) {
        // Calculate match percentages for all jobs
        $jobRecommendations = [];
        
        if (!empty($latestTest['job1'])) {
            $jobRecommendations[] = [
                'job' => $latestTest['job1'],
                'match_percentage' => calculateMatchPercentage($latestTest['job1_confidence'], 0),
                'probability' => $latestTest['job1_confidence']
            ];
        }
        
        if (!empty($latestTest['job2']) && $latestTest['job2'] != '0') {
            $jobRecommendations[] = [
                'job' => $latestTest['job2'],
                'match_percentage' => calculateMatchPercentage($latestTest['job2_confidence'], 1),
                'probability' => $latestTest['job2_confidence']
            ];
        }
        
        if (!empty($latestTest['job3']) && $latestTest['job3'] != '0') {
            $jobRecommendations[] = [
                'job' => $latestTest['job3'],
                'match_percentage' => calculateMatchPercentage($latestTest['job3_confidence'], 2),
                'probability' => $latestTest['job3_confidence']
            ];
        }
        
        if (!empty($latestTest['job4']) && $latestTest['job4'] != '0') {
            $jobRecommendations[] = [
                'job' => $latestTest['job4'],
                'match_percentage' => calculateMatchPercentage($latestTest['job4_confidence'], 3),
                'probability' => $latestTest['job4_confidence']
            ];
        }
        
        if (!empty($latestTest['job5']) && $latestTest['job5'] != '0') {
            $jobRecommendations[] = [
                'job' => $latestTest['job5'],
                'match_percentage' => calculateMatchPercentage($latestTest['job5_confidence'], 4),
                'probability' => $latestTest['job5_confidence']
            ];
        }
        
        // Sort by match percentage (highest first)
        usort($jobRecommendations, function($a, $b) {
            return $b['match_percentage'] - $a['match_percentage'];
        });

        $response['latest_test'] = [
            'top_job' => !empty($jobRecommendations) ? $jobRecommendations[0] : null,
            'all_recommendations' => $jobRecommendations,
            'test_date' => $latestTest['test_date'],
            'skills' => [
                'Logical Reasoning' => floatval($latestTest['critical_thinking']),
                'Problem Solving' => floatval($latestTest['problem_solving']),
                'Technical Skills' => floatval($latestTest['communication']), // Adjusted mapping
                'Communication' => floatval($latestTest['teamwork']), // Adjusted mapping
                'Creativity' => floatval($latestTest['adaptability']) // Adjusted mapping
            ]
        ];
    }

    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    
    error_log("Test History Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error fetching test history'
    ]);
}

$con->close();
ob_end_flush();
?>
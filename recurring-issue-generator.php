<?php

define("TRIM_CHARS", " \n\r\t\v\x00\"");

$githubToken = trim(getenv('GITHUB_TOKEN'), TRIM_CHARS);
$title = trim(getenv('ISSUE_TITLE'), TRIM_CHARS);
$body = trim(getenv('ISSUE_BODY'), TRIM_CHARS);

// Function to get the project configurations from environment variables
function getProjectConfigs() {
    $projects = [];
    foreach (getenv() as $key => $value) {
        if (strpos($key, 'PROJECT_') === 0) {
            list($frequency, $user, $repo) = explode('|', $value);
            $projects[] = [
                'name' => trim(substr($key, 8)),
                'frequency' => trim($frequency, TRIM_CHARS),
                'user' => trim($user),
                'repo' => trim($repo, TRIM_CHARS),
            ];
        }
    }

    return $projects;
}

// Function to create a GitHub issue.
function createGitHubIssue($repo, $user, $githubToken, $title, $body) {
    $data = [
        'title' => $title,
        'body' => $body,
        'assignees' => [$user]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo/issues");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'User-Agent: Rollbar-Log-Issuer'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === FALSE) {
        die('Error creating GitHub issue');
    }

    return json_decode($response, true);
}

// Function to check the last issue created with the same title
function checkLastIssue($repo, $frequency, $githubToken, $title) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/$repo/issues?state=all&per_page=100");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . $githubToken,
        'User-Agent: Rollbar-Log-Issuer'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === FALSE) {
        die('Error fetching issues from GitHub');
    }

    $issues = json_decode($response, true);
    $lastIssueDate = null;

    foreach ($issues as $issue) {
        if ($issue['title'] === $title) {
            $lastIssueDate = $issue['created_at'];
            break;
        }
    }

    if ($lastIssueDate === null) {
        return true;
    }

    $currentDate = new DateTime();
    $lastIssueDate = new DateTime($lastIssueDate);
    $interval = $currentDate->diff($lastIssueDate)->days;

    switch ($frequency) {
        case 'Daily':
            return $interval >= 1;
        case 'Weekly':
            return $interval >= 7;
        case 'Monthly':
            return $interval >= 30;
        case 'Quarterly':
            return $interval >= 90;
        default:
            return false;
    }
}

$projects = getProjectConfigs();
foreach ($projects as $project) {
    $name = $project['name'];
    $frequency = $project['frequency'];
    $user = $project['user'];
    $repo = $project['repo'];

    // Check if it's time to create a new issue based on frequency
    if (checkLastIssue($repo, $frequency, $githubToken, $title)) {
        echo "Attempting to create an issue for $name\n";
        createGitHubIssue($repo, $user, $githubToken, $title, $body);
    }
    else {
        echo "Skipping $name, it's not yet time to notify\n";
    }
}

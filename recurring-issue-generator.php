<?php

const TRIM_CHARS = " \n\r\t\v\x00\"";

$github_token = trim((string)getenv('GITHUB_TOKEN'), TRIM_CHARS);
$title = trim((string)getenv('ISSUE_TITLE'), TRIM_CHARS);
$body = trim((string)getenv('ISSUE_BODY'), TRIM_CHARS);
$label = trim((string)getenv('ISSUE_LABEL'), TRIM_CHARS) ?: 'maintenance';

/**
 * Retrieves the project configurations from the environment variables.
 *
 * This function iterates over the environment variables and extracts the project configurations
 * that start with the prefix 'PROJECT_'. It splits the value of each variable into parts using
 * the '|' delimiter and creates an array of configuration arrays. Each configuration array contains
 * the following keys:
 * - 'name': The name of the project, extracted from the environment variable name.
 * - 'frequency': The frequency of the project, extracted from the first part of the value.
 * - 'user': The user associated with the project, extracted from the second part of the value.
 * - 'repo': The repository associated with the project, extracted from the third part of the value.
 * - 'manager': The GitHub handle of the project manager, extracted from the fourth part of the value.
 *
 * @return array
 *   An array of project configurations.
 */
function get_project_configs(): array {
  $configs = [];
  foreach (getenv() as $key => $value) {
    if (strpos($key, 'PROJECT_') === 0) {
      $parts = explode('|', $value);
      $configs[] = [
        'name' => trim(substr($key, 8)),
        'frequency' => trim($parts[0], TRIM_CHARS),
        'user' => trim($parts[1]),
        'repo' => trim($parts[2], TRIM_CHARS),
        'manager' => isset($parts[3]) ? trim($parts[3], TRIM_CHARS) : NULL,
      ];
    }
  }
  return $configs;
}

/**
 * Calls the GitHub API using the specified HTTP method and URL.
 *
 * @param string $method
 *   The HTTP method to use (e.g., GET, POST).
 * @param string $url
 *   The URL of the API endpoint.
 * @param string $github_token
 *   The GitHub token for authentication.
 * @param array<string, mixed> $data
 *    The data to send with the request (for POST requests).
 *
 * @return mixed
 *   The response from the API as a decoded JSON object.
 * @throws Exception
 *   If there is an error with the cURL request.
 */
function call_github_api(string $method, string $url, string $github_token, array $data = []) {
  $ch = curl_init($url);
  if ($ch === FALSE) {
    throw new Exception('CURL initialization failed.');
  }
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: token ' . $github_token,
    'User-Agent: RecurringIssueGenerator 0.1',
    'Content-Type: application/json'
  ]);
  if ($method === 'POST') {
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  }

  $response = curl_exec($ch);
  if ($response === FALSE) {
    throw new Exception('CURL error: ' . curl_error($ch));
  }
  curl_close($ch);
  return json_decode((string)$response, TRUE);
}

/**
 * Creates a GitHub issue.
 *
 * @param string $repo
 *   The repository name.
 * @param string $user
 *   The username of the assignee.
 * @param string $github_token
 *   The GitHub token for authentication.
 * @param string $title
 *   The title of the issue.
 * @param string $body
 *   The body of the issue.
 * @param string $label
 *   The label for the issue.
 * @param string|null $manager
 *   The GitHub handle of the project manager.
 *
 * @return mixed
 *   The response from the GitHub API as a decoded JSON object.
 * @throws \Exception If there is an error with the GitHub API request.
 */
function create_github_issue(string $repo, string $user, string $github_token, string $title, string $body, string $label, string $manager = NULL) {
  $data = [
    'title' => $title,
    'body' => $body,
    'assignees' => [$user],
    'labels' => [$label]
  ];
  if ($manager) {
    $data['body'] .= "\n\n//cc @" . $manager;
  }
  $issue = call_github_api('POST', "https://api.github.com/repos/$repo/issues", $github_token, $data);
  if (!is_array($issue) || !isset($issue['id'])) {
    throw new Exception('Issue creation failed.');
  }
  return $issue;
}

/**
 * Checks if the last issue with the given title in the specified repository
 * meets the recurrence frequency.
 *
 * @param string $repo
 *   The name of the repository.
 * @param string $frequency
 *   The frequency of the recurrence. Can be 'Daily', 'Weekly', 'Monthly', or
 *   'Quarterly'.
 * @param string $github_token
 *   The GitHub token for authentication.
 * @param string $title
 *   The title of the issue.
 *
 * @return bool
 *   Returns true if the last issue meets the recurrence frequency, false
 *   otherwise.
 *
 * @throws \Exception
 */
function check_last_issue(string $repo, string $frequency, string $github_token, string $title): bool {
  $issues = call_github_api('GET', "https://api.github.com/repos/$repo/issues?state=all&per_page=100", $github_token);
  if (!is_array($issues)) {
    throw new Exception('Failed to fetch issues.');
  }
  foreach ($issues as $issue) {
    if (is_array($issue) && isset($issue['title']) && $issue['title'] === $title) {
      $last_issue_date = new DateTime($issue['created_at']);
      $current_date = new DateTime();
      $interval = $current_date->diff($last_issue_date)->days;

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
          return FALSE;
      }
    }
  }
  return TRUE;
}
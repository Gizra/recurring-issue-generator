# GitHub Issue Creator

[![Build Status](https://app.travis-ci.com/Gizra/recurring-issue-generator.svg?token=XsECVJv635dcj4fku2xo&branch=main)](https://app.travis-ci.com/Gizra/recurring-issue-generator)

This project automates the creation of GitHub issues based on specified frequency and project configurations set through environment variables.

## Features

- Reads project configurations from environment variables.
- Creates GitHub issues based on the specified frequency (Daily, Weekly, Monthly, Quarterly).
- Checks the last issue created with the same title to determine if a new issue needs to be created.
- Can be conveniently integrated and used with Jenkins for automation.

## Environment Variables

The script relies on the following environment variables:

- `GITHUB_TOKEN`: Your GitHub personal access token.
- `ISSUE_TITLE`: The title of the issue to be created.
- `ISSUE_BODY`: The body content of the issue to be created.
- `PROJECT_*`: Environment variables for each project, formatted as `PROJECT_<NAME>="FREQUENCY|USER|REPO"`, where:
  - `<NAME>` is the name of the project.
  - `FREQUENCY` is one of `Daily`, `Weekly`, `Monthly`, or `Quarterly`.
  - `USER` is the GitHub username.
  - `REPO` is the GitHub repository name.

## Usage

1. Clone the repository:
    ```sh
    git clone https://github.com/yourusername/github-issue-creator.git
    cd github-issue-creator
    ```

2. Set up your environment variables. You can create a `.env` file in the project directory:
    ```dotenv
    GITHUB_TOKEN=your_github_token
    ISSUE_TITLE=Your Issue Title
    ISSUE_BODY=Your issue body content
    PROJECT_EXAMPLE="Daily|username|repository"
    ```

3. Run the script:
    ```sh
    php recurring-issue-generator.php
    ```

## Jenkins Integration

This script can be conveniently used within Jenkins for automation. Follow these steps to integrate it into a Jenkins pipeline:

1. **Install the EnvInject Plugin**: This plugin allows you to inject environment variables into the build process.

2. **Create a Jenkins Job**:
    - Configure a new Jenkins job (Freestyle Project or Pipeline).
    - In the **Build Environment** section, check the **Inject environment variables to the build process** option.
    - Provide the path to your `.env` file or specify the environment variables directly.

3. **Add a Build Step**:
    - Add an **Execute shell** build step.
    - In the shell command section, enter the following commands:
      ```sh
      # Navigate to the project directory
      cd /path/to/github-issue-creator

      # Run the PHP script
      php script.php
      ```

4. **Save and Build**: Save the job configuration and run the build. The script will execute and create GitHub issues as configured.

## Functions

- **getProjectConfigs()**: Retrieves the project configurations from environment variables.
- **createGitHubIssue($repo, $user, $githubToken, $title, $body)**: Creates a GitHub issue in the specified repository.
- **checkLastIssue($repo, $frequency, $githubToken, $title)**: Checks the last issue created with the same title and determines if a new issue needs to be created based on the specified frequency.

## Example

Given the following environment variables:
```dotenv
GITHUB_TOKEN=your_github_token
ISSUE_TITLE=Test Issue
ISSUE_BODY=This is a test issue.
PROJECT_PROJECT1="Daily|username1|repo1"
PROJECT_PROJECT2="Weekly|username2|repo2"
```

The script will attempt to create a GitHub issue with the title "Test Issue" and body "This is a test issue." in the repositories repo1 (owned by username1) daily and repo2 (owned by username2) weekly, based on the last issue created with the same title.

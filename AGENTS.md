# General guidelines

Always confirm the OS you're running in before running bash, shell scripts, or Composer commands. If you're running on Windows, review the README.md file to know how to use WSL and Docker to run \*.sh scripts and Composer.

If you're running a command that depends on Docker containers to be running, confirm that the pertinent container is running before trying to run the command. If the containers are not running, request the user to start them and wait for user confirmation before continuing.

When creating a .sh script, if you're running on Windows, make sure to convert the line endings from CRLF to LF before you run it.

If a command fails to run, don't proceed as if the command ran successfully. Prefer to ask the user for confirmation before assuming success on this cases.

# twitter-account-manager

You can mangement multi-twitter accounts by selenium.

# Description

You can automation following.

- tweet
- updateProfile
- setIcon

and more.

# Usage

```sh
$ git clone https://github.com/Rasukarusan/twitter-account-manager.git
$ cd twitter-account-manager

# headless
$ php batch/run.php 1

# non-headless
$ php batch/run.php
```

# Setting

- Run selenium-server-standalone-3.4.0.jar.

```sh
# example
java -jar /Library/java/Extensions/selenium-server-standalone-3.4.0.jar &
```

- Edit account.json

Write your twitter account info.

```sh
$ cd twitter-account-manager
$ cp account_example.json account.json

# Write your twitter account info.
$ vim account.json
```

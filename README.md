# Shifter Artifact Helper

[![CircleCI](https://circleci.com/gh/getshifter/shifter-artifact-helper.svg?style=svg)](https://circleci.com/gh/getshifter/shifter-artifact-helper)

Artifact helper tool for Shifter –  Serverless WordPress Hosting


## Unit test

Path handling (`ShifterUrlsBase::home_path()` / `relative_path()` / `current_url_type()`) is covered by
PHP scripts that stub the WordPress functions they need. No WordPress or PHPUnit installation is required.

```
$ make test
```

These tests cover both root and subdirectory installs (`home_url()` with a path, e.g. `https://example.com/SUBDIR`).

## Integration test

Sandbox

1. import wp data built with template theme-unit-test-data.xml.
2. check `/?urls`

### launch wp for test

```
$ docker pull getshifter/shifter_local:develop
$ make prepare
$ docker-compose build --no-cache
$ docker-compose up
```

open `https://127.0.0.1:8443`

run test for containers which is launched by `docker-compose up`.

```
$ cd integration_test
$ bundle install
$ bundle exec ruby ./entry.rb
```

### update contents for wp

```
$ make prepare
$ docker-compose build --no-cache
$ docker-compose up
```

edit by wp-admin...

after edit.

```
$ docker-compose exec wp /scripts/db_export.sh
```

> Success: Exported to '/mnt/dump/wp.sql'.

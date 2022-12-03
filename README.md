<div align="center">
  <h1>EZ PHP MySQL backup</h1>
  <h2>Simple and fast MySQL backups using PHP</h2>
</div>

<br />

<!-- About the Project -->

## :star2: About the Project

I felt the need for this project when per client request I had to have an automated backup system on a windows 7 32-bit,
but when trying to get other libs to work, I found myself playing a game of whack-a-mole with different bugs and
compatibility issues.

these libs were either using newer software or system commands that were not compatible with windows 7.

I started looking for a pure PHP (no real CLI command/new software needed) that could cover all my needs, and while
myphp-backup did the job, I needed some core and quality-of-life features that it was missing. so I started this project
based on it.

<!-- Getting Started -->

## :toolbox: Getting Started

Simply upload myphp-backup.php script to the DocumentRoot directory of your web application via FTP or other method and run it accessing http://www.example.com/myphp-backup.php. You can also run it from command line.
<!-- Prerequisites -->

### :bangbang: Prerequisites

It requires PHP 5.4 or later.

<!-- Usage -->

## :eyes: Usage

add the library to your code

```php
<?php
// Optional - Report all errors
error_reporting(E_ALL);
// Optional - Set script max execution time
set_time_limit(900); // 15 minutes
// Import the lib
require_once "ez-php-mysql-backup.php";
```
Initialize

```php
$backupDatabase = EzPhpMysqlBackUp::getInstance([
    "db_name" => "your_db_name",
    "ezpmb_gzip" => false,
    "ezpmb_timezone" => 'Asia/Tehran',
]);
```
get a full or conditional backup

```php
// Option-1: Backup tables already defined above
$backupDatabase->backupTables();

// Option-2: Backup changed tables only
$since = '1 day';
$changed = $backupDatabase->backupTablesSince($since);
```

<!-- Roadmap -->

## :compass: Roadmap

* [x] Todo 1
* [ ] Todo 2

<!-- Contributing -->

## :wave: Contributing

Contributions are always welcome!

<!-- License -->

## :warning: License

Distributed under the GNU GPL V3 License.


<!-- Contact -->

## :handshake: Contact

Seyed Mansour Mirbehbahani - sm.mirbehbahani@gmail.com

<!-- Acknowledgments -->

## :gem: Acknowledgements

- [myphp backup](https://github.com/daniloaz/myphp-backup)
- [Awesome Readme Template](https://github.com/Louis3797/awesome-readme-template)

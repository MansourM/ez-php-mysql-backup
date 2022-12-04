<div align="center">
<img src="image/ezpmb_128x512.png" alt="logo" width="512" height="128" />
  <!--<h1>EZ PHP MySQL backup</h1>-->
  <p>Simple and fast MySQL backups using PHP</p>
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

Simply upload myphp-backup.php script to the DocumentRoot directory of your web application via FTP or other methods and
run it by accessing http://www.example.com/myphp-backup.php. You can also run it from the command line.
<!-- Prerequisites -->

### :bangbang: Prerequisites

* PHP 5.4 or later.

<!-- Usage -->

## :eyes: Usage

add the library to your code

```php
<?php
// Optional - DevOnly - Report all errors
error_reporting(E_ALL);
// Optional - Set script max execution time
set_time_limit(900); // 15 minutes
// Import the lib
require_once "ez-php-mysql-backup.php";
```

Initialize with your custom config or use default settings

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
$backupDatabase->backupTablesSince($since);
```

<!-- Roadmap -->

## :compass: Changes from Parent / Roadmap

* [x] Singleton pattern.
* [x] bug fixes, code clean-up, and other minor improvements.
* [x] broke down bigger functions into smaller ones while adding some helper functions.
* [x] improved configuration, now can read from .env/constructor and has default values (used to read from PHP
  constants).
* [x] new config options.
* [x] added magic methods to access configs.
* [x] added log files (all/error) and improved print/output functions.
* [x] bumped to PHP v5.4 (mostly so I can use short array syntax :)
* [x] Better readme
* [x] direct download feature
* [ ] .env.example
* [ ] add config section to the readme
* [ ] cli.php
* [ ] Fix triggers
* [ ] read and get a better understanding of some parts like what happens if tables are being updated mid-backup and how
  best to handle it.
* [ ] test download on large file size / check interaction with execution_time and consider adding a fileReady hook to pass the download link
* [ ] More testing.
* [ ] Code Optimizations.
* [ ] Performance Optimizations.
* [ ] mysqldump?
* [ ] my-php-restore?
* [ ] better comments/docs

<!-- Known Issues -->

## :warning: Known Issues

* error logs needs a \n before, sometimes!
* backUpTriggers does not always work properly

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
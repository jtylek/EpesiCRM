<a href="epe.si">EPESI</a>
=

is a web application server for building CRM/ERP applications. Its base consists of a CRM solution that allows to manage business information: store, organize and share records between people within company or organization. Thus it simplifies internal communication and makes workflow more efficient. It is built on top of a high level, fast and light PHP/Ajax framework which was specially created for that purpose.

The standard features include CRM apps like shared calendar, tasks and address book, an integrated e-mail client Roundcube and unique solutions like advanced permission system, easy form filling (Click2Fill), record change tracking (Watchdog) and full record history.

There are also many extension apps in EPESI offer that deal with more specific needs of different types of business activities. It is thanks to modular design that functionalities of the basic CRM applications can be easily modified and extended. Both free and paid apps can be obtained through EPESI Store. Example of apps: List Manager, Campaign Manager, Inventory Management System, E-Commerce.

Moreover EPESI includes ideas about solving common business processes problems encountered at 400 real businesses and organization in Philadelphia, USA area over 10 years period. Though the apps available in EPESI Store are tested in a real life and they still can be customized to fit other needs.

Since it is a web based application there is no installation required. Once setup it is ready to use, facilitating internal communication. There is no more need to use emails – all data is stored at one place, secure, organized and prioritized in a desired way.

Summing up EPESI CRM allows to store data on servers in a very simple and accessible way, allowing to use the safety and security of advanced computer technologies. In one package with CRM there is a high level PHP framework delivered – a collection of libraries and modules that allow rapid development of new apps. It has a CRUD engine together with user management, lightboxes, forms, themes and more, all with support via forum.

The project has been started in 2006 by Telaxus LLC and the estimated costs to developed it reached $8 mln USD. Though EPESI is open sourced and available for free under the MIT license.

---

Installation

1. REQUIREMENTS
2. CHOOSE INSTALLATION METHOD
3. NEW INSTALLATION USING COMPRESSED FILE
4. NEW INSTALLATION USING EASYINSTALL.PHP SCRIPT
5. REINSTALLATION
6. UPDATE
7. SUPPORT

--- 

1. REQUIREMENTS
    - HTTP web server (apache, IIS) with PHP 5.1.3 support. If possible install the latest PHP version due to several bugs in older versions.
    - PHP 5.2.0 is not supported due to bug in json_decode function. (PHP >= 5.2.1 works)
    - HTTP server should be configured with index.php as one of default documents.
    - HTTP server have to support local .htaccess files
    - PEAR installed with valid include_path in PHP config.ini.
    - MySQL 4+ or PostgreSQL 7+ database server.
    - FTP or local/shell access to the server.
    - A web browser (Chrome or Firefox recommended).

2. CHOOSE INSTALLATION METHOD
    - New installation or update. For update see Update section (6).
    - Installation from compressed file via FTP or local access (shell etc.) - section 3.
    - Easy installation using easyinstall script (preferred method) - section 4.

3. NEW INSTALLATION USING COMPRESSED FILE
    - Download the latest version of EPESI from http://sourceforge.net/projects/epesi/
    - Decompress all files and place them in the directory from which EPESI will be run. You will need to setup /data directory with read/write access.
    - Create a database, note the username, password and database name. Make sure that the user has full rights to the database (read, write, create tables etc.)
    - Point your browser to the location from which EPESI will be run, for example: http://www.yourcompany.com/epesi
    - EPESI setup should start automatically. Accept license agreement and the setup wizard will guide you through all steps which includes creation of the configuration file config.php, necessary directories within /data directory, tables, superadmin user account and password, default data and settings, etc.
    - Finally the setup scans all available modules and you will be greeted with the default dashboard. The installation is complete.
    - Create new users as new contacts and explore the application.
 
4. NEW INSTALLATION USING EASYINSTALL.PHP SCRIPT
    - Create a database, note the username, password and database name. Make sure that the user has full rights to the database (read, write, create tables etc.)
    - Download the latest version of easyinstall script from http://sourceforge.net/projects/epesi/
    - Place the file in the directory from which EPESI will be run. Make sure that the directory has a read/write access. Start the script in a web browser.
    - There is no need to download the entire EPESI application as a compressed file. This easy install script automatically connects to SourceForge server, downloads the latest version, verifies it, decompresses files on the server, sets proper directory permissions and starts EPESI setup.
    - Accept license agreement and the setup wizard will guide you through all steps which includes creation of the configuration file config.php, necessary directories within data directory, tables, superadmin user account and password, default data and settings, etc.
    - Finally the setup scans all available modules and you will be greeted with the default dashboard. The installation is complete.
    - Create new users as new contacts and explore the application.

5. REINSTALLATION
    - By reinstallation we mean complete, new installation of the application without preserving any of the old data. 
    - Open config.php located in /data directory and note the database name, user and the password. You will need to enter the same data during the setup.
    - Delete the entire content of /data directory with the exception of index.html file (which is needed for security reasons).
    - Point your browser to the location from which EPESI was running, for example: http://www.yourcompany.com/epesi
    - During the setup follow instruction above as if it was a new installation.

6. UPDATE
    - Before updating the application backup the entire application directory and especially data directory.
    - Backup the database.
    - Download the new version of EPESI and overwrite all files.
    - Point your browser to the location from which EPESI was running, for example: http://www.yourcompany.com/epesi
    - If the database schema did not change you will be already running new version.
    - If the database schema did change the update process will start automatically during which tables will be altered to this new database schema.
    - Once update process is complete you will be redirected automatically to the new version of EPESI application.

7. SUPPORT
    Any questions, comments and bug reports should be posted on our forum: http://forum.epesibim.com/

Enjoy,
EPESI Team

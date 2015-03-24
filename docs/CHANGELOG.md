EPESI CHANGELOG
===============

(Dev) means that this change is significant only for developers.

RELEASE 1.6.5-20150324
-------

- Fix leightbox prompt id collision
- Fix timestamp field layout
- Fix print templates enabling
- Fix printouts caching by browser
- Fix JS errors related to form focus
- Fix autoloader to use absolute path - fixes Roundcube issues
- Fix calendar event span issues
- Rename Roundcube's archive folders to not use EPESI word
- Clear xcache on module update/install and themeup
- Create function to return default CRM priority and use it for defaults
- Fix RB patches order for update from older versions
- Add method to filter blocked fields from record array (Dev)
- Fix events permission issues in Activities addon and calendar
- Update TCPDF fonts - fixes Chrome blank printout issue
- Clear global cache on themeup
- Fix order by currency field
- Fix filters for currency field on PostgreSQL
- Update CKEditor to version 4.4.7
- Fix memcache session locking issues
- Fix RB crits issue with empty multiselect rule

RELEASE 1.6.4-20150316
-------

- Change cookie expiration time to 7 days for maintenance mode
- Improve update process - make sure maintenance mode is on during patches
- Set default customer for phonecalls, tasks and meetings
- Fix toolbar mode switch in ckeditor
- Add global cache stored in the file (Dev)
- Update ckeditor to the latest version
- Replace CustomRoundcubeAddons with built-in related mechanism
- Fix Roundcube archive copy/paste
- Add option to export RB report to csv file
- Fix issues in currencies admin panel for PostgreSQL database
- Register custom Prototype events also in jQuery (Dev)
- Add selection rules to RecordBrowser
- Currency field - fix frozen value display
- Add option to disable module without uninstall
- Improve /admin script to manage disabled modules
- Base/Print - add more document config params to PDF
- Base/Print - pass printer classname to document_config method (Dev)
- Fix GenericBrowser's default template when expandable is disabled
- Changes in expandable calculation to wrap long text fields - GenericBrowser
- Fix admin access param to get_records method - RecordBrowser
- Fix RB access rules - edit form and add check
- Improve Demo mode security issue check for Base/Print module
- Add filtering in RB for currency, integer and float fields
- Add submodule concept and clean module manager code (Dev)
- Add shared module variables concept (Dev)
- Add custom port for SQL server during installation
- Add resizable columns to GB (georgehristov)
- Add small delay to load tooltips with AJAX
- Add record info tooltips for every default linked label in RB
- Fix access to csv export
- Remove addons during recordset uninstallation
- Add global function to get client IP address
- Translate watchdog email notifications to user's language
- Separate and improve watchdog email template
- Confirm leaving edit form
- Fix bugs in: RB search, module instance name
- Fix recurrent calls to get_val
- Improve select field labels to retrieve nested select values
- Fix PostgreSQL database creation - quote db name

RELEASE 1.6.3-20150107
-------

- Base/Print add method to obtain all templates (Dev)
- Fix recordset uninstall issue caused by words map table constraint 
- Add desktop notifications for Shoutbox, Watchdog and Messenger modules (georgehristov)
- Add desktop notifications possibility (Dev) (georgehristov)
- Login audit - obtain real IP for proxy connections
- Improve module dependencies issue reporting
- Add method Utils_CurrencyFieldCommon::get_all_currencies (Dev)
- Add printer document config (Dev)
- Rename recordset_printer RB patch to fix updates from older EPESI versions
- Whitelabel fixes - replace EPESI text with constant
- Include autonumber fields in default description callback
- Fix duplicate tooltip on field's label - RecordBrowser
- Fix popup calendar event
- Fix locking issues with RB indexer
- Make fields management as a first tab in RecordBrowser's admin
- Sort patches by name (not by path), when no date is supplied in the filename
- Rewrite to JQuery: Utils/Watchdog, Utils/Tooltip (georgehristov)
- Base/Print - change PrintingHandler::output_document to public (Dev)
- Fix fields editor in RecordBrowser - issues with select/multiselect
- Fix display_phone to not create links when nolink is true
- Fix translations in Access Restrictions admin panel
- Fix fields processing order for new fields with *position* set
- Fix date/time crits issues
- Rewrite Session class
- Fix issues with crm_company_contact field edit
- Fix records indexing - create labels with *nolink* param
- Fix Base/Print - buffer PDF output to append footers just once
- Change icon for drag and drop fields sorting
- Add default currency concept
- Add new processing modes to RecordBrowser: edited, deleted, restored
- Fix access issues for autocomplete fields
- Fix desktop notifications to not show shoutbox notifications every time
- Create link to record for autonumber fields
- Add option to jump to new record or not in RB object (Dev)
- Fix indexing when autonumber field has changed
- Fix fields position numbers when removing field
- Keep autonumber position during field edit
- Add method to clear search index for certain tab (Dev)
- Add button to clear search index in RB admin
- Update RoundCube to 1.0.3
- Add related records concept to meetings, tasks and phone calls
- Change table width in RecordBrowser Reports printout
- Fix undefined index issue in RB Reports
- Fix update script to detect glob errors
- Fix admin access - check for method with method exists instead of is callbable
- Fix turkish language issues
- Fix year bug in QuickForm
- Allow to set custom caption for every field in RB

RELEASE 1.6.2-20141020
-------

- Fix like operator on date fields - used by birthdays applet

RELEASE 1.6.2-20141017
-------

- Fix user activity report issues
- Update AdoDB to 5.19
- Fix Roundcube cache issue
- Decrypt note in view - allows to enter crypted note from search
- Fix autoselect filter issue
- Fix tax_id label
- Search file downloads just by token
- Index records for search without cron
- Fix RB select field edit issues
- Fix some RB field edit issues
- Fix handling of relative date crits
- Add new processing callback: browse (Dev)
- Fix time intervals in meetings
- ESS - test connection before registration
- Functions to check database type (Dev)
- Extract SimpleLogin class from admin tools for easy login (Dev)
- Fix setup script for PHP >= 5.6
- Fix blank index page issue
- Fix bad character at the bottom of the page
- Make display_as_row to wrap fields
- Improve module install failure message
- Add method to remove access rules by definition to RecordBrowser (Dev)
- Keep form field focus on soft refresh
- Include Utils/Tray module (Dev)
- Reopen leightbox when error occured in a form
- Add function to replace Base_Box main (Dev)
- Admin tools - add Update Manager to download updates
- Fix translation module to not grow custom translations files
- Update translations

RELEASE 1.6.1-20140913
-------

- Fix dashboard applets removal
- Add field to select from multiple recordsets in RecordBrowser
- Fix attachments PHP 5.3 code issue
- Fix RoundCube addressbook contacts search
- Set Contacts/Access Manager as read-only
- Fix translation functions in Attachments
- Allow negative integer numbers in RecordBrowser
- Set "Company Name" and "Tax ID" fields as unique
- Fix mobile RB edit bug
- Fix Base/Theme get_icon function
- Add Cron Management to Administrator Panel
- Add Custom Recordsets tool to RecordBrowser
- Fix cron CLI detection
- Add who made last edit in attachments display
- Fix Email applet issue with password encoding
- Fix unique email rule
- Add time and timestamp fields to RB GUI admin
- Add datepicker placeholder text
- Fix phonecalls template
- Add ability to sort RB fields with drag n drop
- Update ckeditor version to the most recent
- Add button to switch full toolbar in ckeditor
- Improve patches util error reporting in admin tools
- Maintain QFfields callbacks order during position change
- Configurable edited_on field format in Attachments
- Allow to disable expandable rows in user settings
- Search improvements - optimization, set defaults, disable certain recordsets
- Attachments - do not show password in decrypt form
- RecordBrowser - save filters per user
- RecordBrowser - do not show filter for blocked field
- RecordBrowser - add ability to print any record
- Improve Translation panel
- Fix watchdog notification for notes
- Update translations
- Fix bbcodes in attachments
- RecordBrowser - allow multiple additional actions methods

RELEASE 1.6.0-20140710
-------

- New attachments based on RecordBrowser
- Add exception handling
- Fix Base/Print uninstall method
- Fix attachments when mcrypt module is not loaded
- Do not show files in attachments when note is not decrypted
- Add DEBUG_JS option for better js errors handling
- Add option to forbid autologin
- Add another admin access level to control ban and autologin
- Do not generate watchdog notification when user doesn't have view access to modified field
- Fix Roundcube rc_contactgroups reference
- Fix RecordBrowser's field tooltip for select and multiselect fields
- Fix Month View applet issue related to the daylight saving shift
- Fix new langpack rule issue.
- Remove duplicated codes from countries list and calling codes
- Move jump to id setting to database (remove function Utils_RecordBrowser::disable_jump_to_record)
- Add option to run update procedure from commandline interface
- Add maintenance mode
- Add Utils_CurrencyFieldCommon::parse_currency method
- Improve RB uninstall method to remove processing callbacks and others
- Add option to create mailto: links even when RoundCube accounts are set
- Time management for patches
- Allow patches to save some state and run from that place
- Update process reinvented to match new patches with restart

RELEASE 1.5.6-20140305
-------

- Fix Base/Print filename suffix
- Fix not working RoundCube due to not loaded DBSession class

RELEASE 1.5.6-20140303
----------------------

- Crypted notes
- New module to generate printouts
- Change cron mechanism
- Trigger error, when patch has failed during update
- Fix HomePage template installation
- Add mod_alias rules to show 404 on .svn and .git directories
- Set read-only attribute in commondata
- Fix access restrictions and use proper data directory in check.php script
- Fix logo file in Utils/FrontPage
- Properly sanitize language variable in setup.php script
- Fix get_access method to respect temporary user_id changes
- Fix icon in RecordBrowser for different template
- Extend session_id length
- Allow filtering of custom status in task applet
- Fix commondata edit form - do not allow to override values
- Remove unused code that caused performance issues in CRM/Filters
- Do not validate form in RB during soft submit
- Fix Related Notes company addon
- Fix module_manager to generate proper list of module requirements
- Fix some issues in reset pass script
- Fix TCPDF top margin when logo is set but it's hidden
- Update translations

RELEASE 1.5.5-20140113
----------------------
- Fix recurrent meeting issue in Activities tab - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=2023)
- Fix "Paste company data" button - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=2026)
- Add option to use "Reply-to" header in SMTP settings
- Fix BBCode url matching
- Remove ckeditor's internal save button, that wasn't used
- Fix moving notes - some rare issue with directories
- Fix deleting files upon note removal
- Update RoundCube version to 0.9.5
- Fix dashboard's tab management
- Fix RecordBrowserReports column summary to not show last row doubled
- Fix wrong time and date in mobile view - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=1925)
- Add new possible Home Page - company of current user
- Check access when copying company data into contact
- Clean up include path
- Fix creating new contact - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=2082)
- Fix calendar event with duration less than 1h
- Several fixes for PostgreSQL engine
- Fix broken Contact's template (#2)
- Fix printing all records from RecordBrowser
- Fix watchdog email notifications (#3)
- Update translations

RELEASE 1.5.4-rev11060 (20131015)
---------------------------------
- update translations
- bugfixes to problems reported since original 1.5.4 release

RELEASE 1.5.4-rev11044 (20131014)
---------------------------------
- RoundCube 0.9.4
    **Warning** New RoundCube client requires PDO extension enabled in php.ini and PHP 5.2.1 or greater. When using MySQL database it **requires PHP version 5.3 or higher**
- fixed bugs in RecordBrowser and Attachments
- changed admin view for currencies
- do not report E_DEPRECATED errors - PHP 5.5.x [deprecates some features](http://php.net/manual/en/migration55.deprecated.php) used by Smarty templating engine
- EPESI - RoundCube archiving fixes
- RoundCube imap cache fixes
- fix RecordBrowser's field edit error when param is empty
- use reply-to header as default when sending emails from EPESI
- fix time issues in mobile view - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=1925#p7132)
- improve CSV export

RELEASE 1.5.3-rev10944 (20130709)
---------------------------------
- fix calendar month view in certain timezone configuration - [forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=1523&p=5959#p5959)
- fix adding new record - rare issue
- add patch to create one of the ban variables - sometimes after installation admin could get error "undefined variable"
- fix template html for launchpad
- fix deprecated hook name in RoundCube EPESI plugin
- fix leightbox js issues
- fix searching for a lot of records
- sort meetings in activities tab
- fix issues with field names in record's history
- add filtering for currency field
- RBO - add set_style method for field definition, add get_access method to recordset
- fix add note from table view and record view - [forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=1760)
- updated translations

RELEASE 1.5.2-rev10766 (20130513)
---------------------------------
- Full version of CKEditor included.
- Fixed bugs:
    - commondata field created by user was causing error during search - [Forum thread](http://forum.epesibim.com/viewtopic.php?f=6&t=1678)
    - tooltips in calendar events were broken - [Polish forum thread](http://forum.epesibim.com/viewtopic.php?f=25&t=1685)
    - print browse mode of company or contact field didn't indicate record type.
- Icon of company or contact field has been changed to text indicator ([Company] / [Contact]) in some places. It's related to third bug listed above.

RELEASE 1.5.1-rev10757 (20130508)
---------------------------------
- A new version of CKEditor
- Fixed bug in Utils/Attachments - user was unable to edit note using Firefox.
    Now notes edit box is always on top of the notes.
- Updated translations

RELEASE 1.5.0-rev10738 (20130424)
-------------------------------
USER PERSPECTIVE
- new RoundCube email client
- new CKEditor version - modern look & feel
- click2fill appearance and help improvements
- multiple attachments per note
- shoutbox improvements - click to address person, changed user labels, tab+enter to send
- company or contact suggestbox - show icon based on type, always display several records from both recordsets
- watchdog - subscribe to categories (by default only for managers)
- sort mails archived in EPESI by thread

ADMIN PERSPECTIVE
- User ban system improvements and restore controls in administrator panel
- add option to disable EPESI store to faster module administration launch
- changed install process - allow translating from first screen
- allow run /admin tools before Base installation
- add option to set security in smtp server settings
- improved RecordBrowser fields administration
- changed HomePage mechanism - allow to set default home page for specific group of users
- link from Administrator panel to /admin tools
- add EPESI shell in /admin tools - disabled by default
- add patch utility in /admin tools

DEVELOPERS PERSPECTIVE
- RecordBrowser - allow disable "jump to record"
- RecordBrowser - add autonumbering field type
- new types for RBO - company, contact, employee, company or contact, email, time, phone
- allow to translate strings from smarty templates

SYSTEM
- RoundCube 0.8.2 with several EPESI integration fixes
- CKEditor 4.0.2
- optimize startup time
- allow to translate /admin tools
- interactive help system
- fixed automulti suggestboxes to display all selected fields
- attachments bug fixes
- display errors by default (config.php)
- RecordBrowser - fix permission check issues
- fix search engine for contacts and companies
- partial rewrite to jQuery (we are going to remove Prototype)
- several PostgreSQL fixes (thanks to forum user - pedro42)
- fixed EpesiStore on PHP 5.2.6 - [php.net](https://bugs.php.net/bug.php?id=45028)
- add option to store session in files instead of database
- appearance bug fixes
- translations improved - more string have been marked to translate
- clean up some parts of code

IMPORTANT NOTES
- PHP 5.2.0 is not supported due to bug in json_decode function. (PHP >= 5.2.1 and PHP < 5.2.0 works)
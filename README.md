# web3Backup Tool

Homepage: [http://www.exteon.ro/en/products/programming-tools/web3backup](http://www.exteon.ro/en/products/programming-tools/web3backup)

**The web3Backup Tool is a tool written in PHP that allows automated backups of parts of a Linux system.**

Its main features are:

* Automated backup of resources: SVN, Hg, MySQL, SQLite, directories and files
* Text dumps of SVN, MySQL and SQLite databases using svnadmin and mysqldump
* Full directory backups, for configuration and user generated files
* Daily, weekly and monthly backups, with consistent rotation schema and separate configurable backup items
* Binary incrementally backups for any of the above resources, using [rdiff-backup](http://www.nongnu.org/rdiff-backup/)

**Changelog**
We don't (yet) use semantic versioning numbers for this project. Version numbers consist of the date a version is published on github.

<u>v2019-01-05</u>
* Added exceptions_glob, exceptions_regexp parameters for "file" backups

<u>v2014-12-09</u>
* Added support for SQLite and Hg
* Added possibility to export all MySQL databases by leaving the dbs field empty
* Speed up Hg incremental exports; Hg uses an unorthodox technique of hardlinking repository db files on Linux, which works against rdiff-backup's modification detection efficiency (and generates useless metadata). Fixed by generating dumps using clone --pull and touching modification date to 1970-01-01
* Improved rotated files naming; note it is not consistent with the previous namings so previous rotation dumps will not be rotated.

**Usage**

The script should be run daily via a cron job either directly or via a script like the doBackup.sh sample runner provided. Upon running,
it will generate in the target backup folder a structure like:

    2014-07-13 13-22-45 dwmy
        cvs
            svn
                sha1__var_svn
    2014-07-15 13-22-45 d__y
    ...
    incremental
        files
            sha1__var_www_html_uploads


Each backup folder can be one or more of a daily, weekly, monthly or yearly backup, which is represented by the "dwmy" or "d__y" above after the date.
These backup dirs are rotated by a configurable scheme. All resources backed up in them are .tar.gz-ipped.
There is also the incremental folder which hosts binary incremental backups generated using rdiff-backup; this directory is not compressed,
and contains a most recent snapshot of the backed up files.

To set up this kind of automated backup, you must first edit the backup.config.php file and set it up. A sample is shown below:

    ```php
		$GLOBALS['backupConfig']=array(
			/*
			 * tempDir is the path to a local temporary dir where VCS and DB dumps can be generated; it is not
			 * advisable to use /tmp based directories, as most systems have a RAM mounted filesystem there. 
			 * Use rooted path, no trailing slash
			 */
			'tempDir'=>'/tempBackup',
			/*
			 * targetDir is a local path where backups will be stored. It can be the path to a remotely-mounted
			 * filesystem. There are no particular requirements on the filesystem features. 
			 * Use rooted path, no trailing slash
			 */
			'targetDir'=>'/remoteBackup',
			/*
			 * The "when" collection specifies when backups are generated. It has the following structure:
			 * 
			 *   daily
			 *     rotate      - amount of time (in days) after which daily backups are deleted
			 *   weekly
			 *     weekday     - weekday (Sunday=0) when weekly backups are generated
			 *     rotate      - amount of time (in weeks) after which weekly backups are deleted
			 *   yearly
			 *     month       - month of year on which yearly backups are generated
			 *     day         - day of the prevoius said month on which yearly backups are generated
			 *     rotate      - amount of time (in years) after which yearly backups are deleted
			 * 
			 * If any of the main keys are set to false, the backup tool will not generate that kind of backup
			 */
			'when'=>array(
				'daily'=>array(
					'rotate'=>14
				),
				'weekly'=>array(
					'weekday'=>0,		//	0=Sun
					'rotate'=>9
				),
				'monthly'=>array(
					'day'=>31,
					'rotate'=>24
				),
				'yearly'=>array(
					'month'=>12,
					'day'=>31,
					'rotate'=>false
				)
			),
			/*
			 * The "what" array contains any number of entries that represent items to backup. Each entry can have these
			 * general fields, and then based on type, a number of other additional fields described below:
			 *   type            - Identifies the resource type; see the types below for a rundown of supported types
			 *   when            - A comma-delimited string or a string array specifying when the said resource should be
			 *                     backed up; can contain any combination of "daily", "weekly", "monthly", "yearly".
			 *                     Default is "daily". "daily" usually implies all others, unless the daily backup is
			 *                     disabled from the "when" collection above.
			 *                     Please note the following limitation: there is no logic to detect overlaps between
			 *                     different items specifying the same resource; you might be temped to define:
			 *                     array(
			 *                         'type'=>'db/mysql',
			 *                         'dbs'=>'db1',
			 *                         'when'=>'weekly'
			 *                     ),                     
			 *                     array(
			 *                         'type'=>'db/mysql',
			 *                         'dbs'=>'db1,db2',
			 *                         'when'=>'monthly'
			 *                     )
			 *                     Since only one backup is generated per any pass, when a backup is generated that is
			 *                     both weekly and monthly, db1 will be dumped twice (the second overwriting the first).
			 *                     Such situation should be avoided. We may add later a functionality to discard redundant 
			 *                     resource backups.
			 *   mode            - Can be "incremental", specifying that the resource should be backed up incrementally
			 *                     using rdiff-backup. For this to work, rdiff-backup must be installed and in PATH. Note
			 *                     when using with the "file" type that files cannot be incrementally backed up, only 
			 *                     folders. The "incremental" mode works with DB and VCS dumps. If it is not specified,
			 *                     regular rotated backups are used. 
			 *                     When using "incremental" backups, you can specify one more parameter: "purgeAfter"
			 *                     is the interval in days the binary diffs should be kept.
			 *   purgeAfter      - If "mode" is "incremental" (and valid), this specifies the interval in days for which 
			 *                     the binary diffs should be kept
			 * ============================
			 *   type=>"vcs/svn" - Dumps a SVN repository, using the svnadmin export command. Needs svnadmin to be installed
			 *                     and in PATH
			 *   path            - The local filesystem path where the repository is located
			 * ============================  
			 *   type=>"vcs/hg"  - Dumps a HG repository, using the hg clone --pull command. Needs hg to be installed
			 *                     and in PATH
			 *   path            - The local filesystem path where the repository is located
			 * ============================  
			 *   type=>"db/mysql" - Dumps mysql databases using the mysqldump tool. Requires svndump to be installed and in
			 *                     PATH
			 *   host            - MySQL host; default "localhost"
			 *   user            - MySQL user; default "root"
			 *   password        - MySQL user's password
			 *   dbs             - Collection of databases to be dumped; can be specified as a comma-separated string or
			 *                     as a string array; if empty, all databases are exported
			 * ============================
			 *   type=>"db/sqlite" - Dumps sqlite databases using the sqlite3 dump commandtool. Requires sqlite3 to be installed 
			 *                     and in PATH
			 *   path            - The local filesystem path where the DB is located
			 * ============================
			 *   type=>"file"    - Backs up a local file or folder
			 *   path            - Local path to file/directory; rooted, no trailing slash
			 *   exceptions_glob - Array of strings containing paths (relative to the path, no leading slash) that should
			 *                     be excepted from the backup, in glob match format.
			 *   exceptions_regexp - Array of strings containing paths (relative to the path, no leading slash) that should
			 *                     be excepted from the backup. The paths are in regexp format (not glob). Note that expressions
			 *                     will be terminated ("$" added) so you MUST add regexp syntax to match the whole filename 
			 *                     (i.e. trailing "/.*") if you intend to except whole directories.
			 *                     NOTE: For non-incremental backups "exceptions" is not supported yet, use "exceptions_glob"
			 *                         instead!
			 *                     Examples:
			 *                         'Temp/.*'               - excludes the whole "Temp" folder
			 *                         '.*/\\.svn/.*'          - excludes every ".svn" folder in any subdirectory
			 *                         '.*/[^/]*\\.bak(/.*)?'  - excludes files or directories named "*.bak" in any subdirectory
			 * ============================ 
			 */
			'what'=>array(
				array(
					'type'=>'vcs/svn',
					'path'=>'/var/svn',
				),
				array(
					'type'=>'vcs/hg',
					'path'=>'/var/hg/somerepo',
				),
				array(
					'type'=>'db/sqlite',
					'path'=>'var/dbs/somedb.sqlite'
				),
				array(
					'type'=>'db/mysql',
					'host'=>'localhost',
					'user'=>'root',
					'password'=>'pass',
					'dbs'=>array(
						'somedb1',
						'somedb2',
						'somedb3'
					),
					'when'=>'daily'
				),
				array(
					'type'=>'db/mysql',
					'host'=>'localhost',
					'user'=>'root',
					'password'=>'pass',
					'dbs'=>array(
						'somedb4'
					),
					'when'=>'monthly,yearly'
				),
				array(
					'type'=>'file',
					'mode'=>'incremental',
					'path'=>'/home/someuser',
					'purgeAfter'=>31,
					'exceptions_glob'=>[
						'**/.exclude'
					]
				)
			)
		);
    ```

There is also provided a sample shell script that mounts a NFS share, runs the backup as an unprivileged user and then unmounts the NFS 
share down when complete.
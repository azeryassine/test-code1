# Pimcore Installation

The following guide assumes your're using a typical LAMP environment, if you're using a different setup (eg. Nginx) or facing a problem, please visit the [Installation Guide](../13_Installation_and_Upgrade/README.md) section.

## 1. System Requirements
Please have a look at [System Requirements](../13_Installation_and_Upgrade/01_System_Requirements.md) and ensure your system is ready for Pimcore. 

## 2. Install Pimcore Sources
The easiest way to install Pimcore is from your terminal using our installer package. 
We additionally provide a [Composer based install guide](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/04_Composer_Install.md) and of course you can install Pimcore also without the help of the command line using your favorite tools (SFTP, ...).

Change into the root folder of your project (**please remember project root != document root**): 
```bash
cd /your/project
```

Pimcore is offering [2 installation packages](https://www.pimcore.org/download) for different use-cases: 
  
| Version | Description |  
|--------------------|---------------------------------------------------------------------------------|
| **Latest Release** | The latest release version of Pimcore. Good choice for all purposes ;-)         |
| **Nightly Build**  | Daily released version. Shouldn't be used in production but ok for development. |
  
  
#### Download a Package: 
```bash
wget https://www.pimcore.org/download-5/pimcore-unstable.zip -O pimcore-install.zip
```

Unzip the installer package into the current folder (project root): 
```bash
unzip pimcore-install.zip
```

Point the document root of your vhost to the newly created `/web` folder out of the ZIP archive (eg. `/your/project/web`). 
Keep in mind, that Pimcore needs to be installed **outside** of the **document root**.
Specific configurations and optimizations for your webserver are available here: 
[Apache](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/01_Apache_Configuration.md),
[Nginx](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/02_Nginx_Configuration.md)

Pimcore requires write access to the following directories (relative to your project root): `/var`, `/web/var` and `/pimcore` 
([Details](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/03_File_Permissions.md)) 


> No CLI? Click [Latest Release](https://www.pimcore.org/download/pimcore-latest.zip) or 
[Nightly Build](https://www.pimcore.org/download/pimcore-data.zip) to download the package with your browser 
and extract/upload Pimcore manually on your server (outside document root!). 


## 3. Create Database
```bash
mysql -u root -p -e "CREATE DATABASE project_database charset=utf8mb4;"
```

For further information please visit out [DB Setup Guide](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/05_DB_Setup.md)

## 4. Launch Pimcore and Finish Installation
Finish the Pimcore installation by accessing the URL (eg. `https://your-host.com/install.php`) in your web browser. 
1. Fill in the required fields (database + admin user)
2. Press ***Check Requirements*** to check if your system is ready for Pimcore
3. Click ***Install Now!*** 
4. Ensure the the file `web/install.php` was moved out of `web/` by the installer, if not, (re)move it manually. 


## 5. Maintenance Cron Job
```text
*/5 * * * * /your/project/bin/console maintenance
```
Keep in mind, that the cron job has to run as the same user as the web interface to avoid permission issues (eg. `www-data`).

## 6. Additional Information & Help
If you would like to know more about installation process or if you are having problems getting Pimcore up and running, visit 
the [Installation Guide](../13_Installation_and_Upgrade/README.md) section.

## 7. Further Reading
- [Apache Configuration](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/01_Apache_Configuration.md)
- [Nginx Configuration](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/02_Nginx_Configuration.md)
- [Database Setup](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/05_DB_Setup.md)
- [Additional Tools Installation](../13_Installation_and_Upgrade/03_System_Setup_and_Hosting/06_Additional_Tools_Installation.md)

Next up - [Directories Structure](./02_Directories_Structure.md)

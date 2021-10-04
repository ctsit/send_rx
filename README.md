# Send Rx

Send Rx is a REDCap module that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Prerequisites
- REDCap >= 8.0.3
- [Composer](https://getcomposer.org/)
- [REDCap User Profile](https://github.com/ctsit/redcap_user_profile)
- [DAG Switcher](https://github.com/lsgs/redcap-dag-switcher)

## Manual Installation
- Clone this repo into to an `<redcap-root>/modules/send_rx_v<version_number>`.
- Go to **Control Center > External Modules** and enable Send Rx.
- Automated installation of Composer dependencies (required)
  send\_rx assumes composer dependencies have been installers in `<redcap-root>/vendor`.
  The redcap\_deployment packaging tools do this by default.  We recommend you use
  them--at least once--to assure that composer-installed libraries are installed in
  the correct location.
- Manual installation of Composer dependencies (optional)
  - In a terminal, go to your REDCap root directory
  - [Download Composer](https://getcomposer.org/download/)
  - Run `php composer.phar install`

## Configuration
The steps below will walk you through a study research use case.

### Step 1: Making sure that user authentication is enabled
Send Rx requires user authentication method to work, so if your REDCap does not have it, you may need to follow the steps below:

1. Go to **Control Manager > Add Users (Table-based Only)**
2. Add a new user that will be the new admin account (since `site_admin` will become deprecated)
3. Go to **Control Manager > Administrators & Acct Managers** and add the new user to the administrators list
4. Go to **Control Manager > Security & Authentication**, select an authentication method of your choice (e.g. Table-based), and save
5. Check your email inbox and look for a "REDCap access granted" email
6. Open the email contents, and click on "Set your new REDCap password" link
7. Set your password
8. Go to **Control Manager > Administrators & Acct Managers** and remove deprecated `site_admin` from administrators list

### Step 2: Creating User Profiles project
1. Access **+ New Project** page, then import `samples/UserProfiles.xml` file.
2. If User Profile module is not enabled yet, you may do that by accessing **Control Center > External Modules**.
3. Then on **Control Center > External Modules**, configure the module as follows:
  - Project: User Profiles (or any name you might have given to the project)
  - Username field: `send_rx_user_id`

### Step 3: Creating Sites Project
1. Make sure you are logged in as the admin user created on step 1 (not `site_admin`)
2. Access **+ New Project** page, then import `samples/SendRxSites.xml` file.
3. Go to **External Modules** section and enable Send Rx module for this project
4. Then on External Modules page, click on Send Rx **Configure** button and set fields as follows:
  - Type: Site
  - Target Project: (Leave it blank for now, you are going to set it on step 4.7)
  - PDF Template Name: (upload `SamplePDFTemplate.html`) file
  - PDF Template Variables:
    - Key: "study_irb", Value: "2017-1234"
    - Key: "study_name", Value: "Sample Study"
  - Message subject: "Test prescription"
  - Message body: "The prescription file is available at: [patient][send_rx_pdf]"

### Step 4: Creating Patients Project
1. Make sure you are logged in as the admin user created on step 1 (not `site_admin`)
2. Access **+ New Project** page, then import `samples/SendRxPatients.xml` file.
3. Go to **External Modules** section and enable Send Rx module for this project
4. Enable DAG Switcher for this project
5. Then on External Modules page, click on Configure Button and set fields as follows:
  - Type: Patient
  - Target Project: the Sites project defined on section 3
6. Go to **User Rights** section and create two roles: `prescriber` and `study_coordinator`
7. Switch to Sites project, then access **External Modules** and click on Send Rx **Configure** button
8. Set **Target Project** as the project you just imported.

## Sending your First Test Prescription

### Step 1: Create a Site/Pharmacy
1. On site project, go to **Add / Edit records** and then click on **Add new record**.
2. On **Site Information** form, fill out site name, then select `Email` as delivery type, then set the email address you want to use in your test, and finally save - making sure sure your form is set as *Complete*.
4. On **Site Staff** step, select **Create a new user account from scratch**, fill out user information, make sure your form is set as *Complete*, then click on **Save & Go To Next Instance**
5. Repeat the step above a few times - making sure to add at least one prescriber and one study coordinator - then click on **Save & Exit**
6. Go to **Record Status Dashboard** where you should be able to see two buttons: **Rebuild staff permissions** and **Revoke staff permissions** (if both buttons are disabled, make sure all forms previously filled are set as *Complete*, i.e. they appear as green bullets)
7. Click on **Rebuild staff permissions** to grant permissions to your staff
8. You may need to navigate to the **Patients** project and assign your coordinator and prescriber user(s) to the proper DAG

### Step 2: Create a Prescription and Send it
1. Log in as study coordinator
2. On patient project, go to **Add / Edit records** and then click on **Add new record**
3. Fill out all forms until the last one - **Review & Send Rx** - then click on **Send and Stay**
4. At **Messages History** block you should now see the notification contents you just sent
5. Check your email inbox

## Customizing PDF and email messages

The presented example can be fully adapted to your needs. You may freely create your own PDF template, change the email contents configuration, and override all forms/instruments (as soon as the fields containing `send_rx_` prefix remain untouched). All form fields you update/create will available to be used as wildcards on PDF and email (e.g. `[patient][first_name]`, `[site][send_rx_name]`, `[prescriber][first_name]`, etc).

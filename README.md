# Send Rx

Send Rx is a REDCap module that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Prerequisites
- REDCap >= 8.0.0 (for versions < 8.0.0, [REDCap Modules](https://github.com/vanderbilt/redcap-external-modules) is required).
- [Composer](https://getcomposer.org/)
- [REDCap User Profile](https://github.com/ctsit/redcap_user_profile)

## Installation
- Clone this repo into to an `<redcap-root>/modules/send_rx_v1.0`.
- Go to **Control Center > Manage External Modules** and enable Send Rx.
- Run Composer in order to install third-party dependencies:
  - In a terminal, go to your Send Rx root directory
  - [Download Composer](https://getcomposer.org/download/)
  - Run `php composer.phar install`

## Configuration
The steps below will walk you through a study research use case.

### Step 1: Making sure that Table-based authentication is enabled
Send Rx requires Table-base authentication method to work, so if your REDCap does not have it, you may need to follow the steps below:
1. Go to **Control Manager > Add Users (Table-based Only)**
2. Add a new user that will be the new admin account (`site_admin` will be deprecated)
3. Go to **Control Manager > Administrators & Acct Managers** and add the new user to the administrators list
4. Go to **Control Manager > Security & Authentication**, change authentication method to "Table-based", and save
5. Check your email inbox and look for a "REDCap access granted" email
6. Open the email contents, and click on "Set your new REDCap password" link
7. Set your password
8. Go to **Control Manager > Administrators & Acct Managers** and remove deprecated `site_admin` from administrators list

### Step 2: Creating User Profiles project
1. Access **+ New Project** page, then import `samples/UserProfiles.xml` file.
2. If User Profile module is not enabled yet, you may do that by accessing **Control Center > Manage External Modules**.
3. Yet on **Control Center > Manage External Modules**, configure the module as follows:
  - Project: User Profiles (or any name you might have given to the project)
  - Username field: `send_rx_user_id`

### Step 3: Creating Sites Project
1. Make sure you are logged in as the admin user created on step 1 (not `site_admin`)
2. Access **+ New Project** page, then import `samples/SendRxSites.xml` file.
3. Go to **Manage Extensions** section and enable Send Rx module for this project
4. Yet on Manage Extensions page, click on Send Rx **Configure** button and set fields as follows:
  - Type: Site
  - Target Project: (Leave it blank for now, you are going to set it on step 4.8)
  - PDF Template Name: (upload `SamplePDFTemplate.html`) file
  - PDF Template Variables:
    - Key: "study_irb", Value: "2017-1234"
    - Key: "study_name", Value: "Sample Study"
  - Message subject: "Test prescription"
  - Message body: "The prescription file is available at: [patient][send_rx_pdf]"

### Step 4: Creating Patients Project
1. Make sure you are logged in as the admin user created on step 1 (not `site_admin`)
2. Access **+ New Project** page, then import `samples/SendRxPatients.xml` file.
3. Take note of your new project ID (you should see it at the `pid` parameter in your URL).
4. Go to **Manage Extensions** section and enable Send Rx module for this project
5. Yet on Manage Extensions page, click on Configure Button and set fields as follows:
  - Type: Patient
  - Target Project ID: _Place here the PID from step 3.2_
6. Go to **User Rights** section and create two roles: `prescriber` and `study_coordinator`
7. Switch to Sites project, then access **Manage Extensions** and click on Send Rx **Configure** button
8. Set **Target Project** as sites project defined on step 3 and save.

## Sending your First Test Prescription

### Step 1: Creating a few test users
1. Go to **Control Manager > Add Users (Table-based Only)** and create a few test users.
2. Go to **Control Manager > Browse Users** and click on **View Users**.
3. For each account you created:
  - Access its details page;
  - Click on **Create user profile** button;
  - Fill and submit the user profile information.

### Step 2: Create a Site/Pharmacy
1. On site project, go to **Add / Edit records** and then click on **Add new record**.
2. Fill **Site Information** form step and go ahead to the next step
3. On **Delivery methods step**, choose `Email` as the delivery type, then fill the email address you want to use in your test, and save.
4. On **Site Staff** step, add the test users you created previously (make sure to add prescribers and at least one study coordinator), then click on **Save & Exit**
5. On record home page, will might be able to see two buttons: **Rebuild staff permissions** and **Revoke staff permissions**
6. Click on **Rebuild staff permissions** to grant permissions to your staff

### Step 3: Create a Prescription and Send it
1. Log in as study coordinator.
2. On patient project, go to **Add / Edit records** and then click on **Add new record**
3. Complete all steps until the last step (**Review & Send Rx**).
5. On review step, click on **Send and Stay**
6. At **Messages History** block you should now see the notification contents you just sent.
7. Check your email inbox.

## Customizing PDF and email messages

The presented example can be fully adapted to your needs. You may freely create your own PDF template, change the email contents configuration, and override all forms/instruments (as soon as the fields containing `send_rx_` prefix remain untouched). All form fields you update/create will available to be used as wildcards on PDF and email (e.g. `[patient][first_name]`, `[site][send_rx_name]`, `[prescriber][first_name]`, etc).

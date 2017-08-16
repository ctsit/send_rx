# Send Rx

Send Rx is a REDCap extension that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Prerequisites

This project depends on the following libraries to work:
- [REDCap Custom Project Settings Plugin](https://github.com/ctsit/custom_project_settings)
- REDCap Linear Data Entry Workflow

## Installation

### Option 1: With Redcap Deployment tool

If you are deploying the extension using UF CTS-IT's [redcap_deployment](https://github.com/ctsit/redcap_deployment) tools ([https://github.com/ctsit/redcap_deployment](https://github.com/ctsit/redcap_deployment)), you can activate these extensions with those tools as well. If you have an environment named `vagrant` the activation would look like this:

```
fab instance:vagrant activate_hook:redcap_save_record,send_rx_save_record_hook.php
fab instance:vagrant activate_hook:redcap_data_entry_form_top,send_rx_data_entry_form_top_hook.php
fab instance:vagrant activate_hook:redcap_every_page_before_render,send_rx_every_page_before_render_hook.php
fab instance:vagrant activate_hook:redcap_every_page_top,send_rx_every_page_top.php
fab instance:vagrant activate_hook:redcap_data_entry_form_top,add_default_on_visible_action_tag.php
```

### Option 2: Other Environments

The hooks are dependent on a framework that calls _anonymous_ PHP functions such as UF CTS-IT's [Extensible REDCap Hooks](https://github.com/ctsit/extensible-redcap-hooks) ([https://github.com/ctsit/extensible-redcap-hooks](https://github.com/ctsit/extensible-redcap-hooks)). If you are not using such a framework, each hook will need to be edited by changing `return function($project_id)` to `function <hook_function_name>($project_id)` where the _hook\_function\_name_ should be that referenced in the relevant activate_hook line above.


### Developer Notes

When using the local test environment provided by UF CTS-IT's [redcap_deployment](https://github.com/ctsit/redcap_deployment) tools ([https://github.com/ctsit/redcap_deployment](https://github.com/ctsit/redcap_deployment)), you can use the deployment tools to configure the extension for testing in the local VM. If you clone this repo as a child of the `redcap_deployment` repo, you can activate the hook and plugin for testing from the root of the `redcap_deployment` repo like this:

```
fab instance:vagrant test_hook:redcap_save_record,send_rx/send_rx_save_record_hook.php
fab instance:vagrant test_hook:redcap_data_entry_form_top,send_rx/send_rx_data_entry_form_top_hook.php
fab instance:vagrant test_hook:redcap_every_page_before_render,send_rx/send_rx_every_page_before_render_hook.php
fab instance:vagrant test_hook:redcap_every_page_top,send_rx/send_rx_every_page_top.php
fab instance:vagrant test_hook:redcap_data_entry_form_top,send_rx/add_default_on_visible_action_tag.php
```

## Configuration

In order to activate Send Rx extension, we need to create and configure two projects:
- **Site**: Stores Site details including the pharmacy used and prescribers and study coordinators at each site.
- **Patient**: Provides the Patient details and prescription form. Once submitted, the prescription is sent to the pharmacy.

### Step 1: Creating Sites Project
1. Access **+ New Project** page, then import `SendRxSites.xml` file.
2. Take note of your new project's ID (you should see it at the URL's `pid` parameter).
3. Access **File Repository** page, then go to **Upload New File** tab
4. Upload `pdf_template_complete.html` file provided by this repository, name it as `SamplePDFTemplate`, and save.
5. Go back to **Project Setup** page, then access **Custom Project Settings**
6. Add a config entry called `send_rx_config` as JSON string, with keys like these:
```
{
    "type": "site",
    "targetProjectId": 24,
    "pdfTemplate": "SamplePDFTemplate",
    "messageSubject": "Test prescription",
    "messageBody": "<div>The prescription file is available at: [patient][send_rx_pdf]</div>",
    "variables": {
        "study_irb": "2017-1234",
        "study_name": "Sample Study"
    }
}
```

The keys are described as follows:
- `type`: The project type of _this_ project.
- `targetProjectId`: The project ID of the patient project
- `pdfTemplate`: The name of PDF prescription template file you entered on step 4.
- `messageSubject`: The subject line of the email message that will alert the pharmacist to a new prescription to be filled.
- `messageBody`: The body of the email message that will alert the pharmacist to a new prescription to be filled. You can include the names of objects and fields to be substituted in the body.  e.g., [patient][send_rx_pdf]

There is a [full section](#templating-pdfs-and-messages) dedicated to explain PDFs and messages templating, but let's put this aside for a while and move on to the next step.


### Step 2: Creating Patient Project
This is quite analogous to what we just did on previous section.

1. Access **+ New Project** page, then import `SendRxPatients.xml` file.
2. Go to **Custom Project Settings** of your new project and create a config entry called `send_rx_config` as a JSON string with keyes like these:
```
{
    "type": "patient",
    "targetProjectId": 23
}
```

The keys are described as follows:
- `type`: The project type of _this_ project.
- `targetProjectId`: The project ID of the _sites_ project
- _(optional)_ `lockInstruments`: You might add some edit restrictions to the prescribers once the prescription is done. On this case, you may set a comma separated list of instruments (i.e. form steps names) to be locked after the message is sent (e.g. `lab_orders,prescriptions`).
- _(optional) (For developers use)_ `senderClass`: The PHP class responsible to create prescription PDFs and send messages to the pharmacies. This option opens the possibility of extending the default send engine (`RxSender` class) in order to satisfy all project's needs. Defaults to `RxSender`.

With optional parameters, the JSON contents could look like this:
```
{
    "type": "patient",
    "targetProjectId": 23,
    "lockInstruments": "patient_demographics,prescription",
    "senderClass": "RxSender"
}
```


## Sending your First Test Prescription

### Step 1: Create a Pharmacy
1. On pharmacy project, go to **Add / Edit records** and then click on **Add new record**.
2. Fill **Pharmacy Information** form step and submit it
3. On **Delivery methods step**, choose `Email` as the delivery type, then fill the email address you want to use in your test, and save.
4. On **Prescribers** step, assuming you are logged as admin, fill **Username** field as `site_admin`.
5. Complete and submit your pharmacy registration.

### Step 2: Create a Prescription and Send it
1. On patient project, go to **Add / Edit records** and then click on **Add new record**
2. Complete all steps until the last step (**Generate & Send prescription**).
3. On **Send RX Notification** step, set **Pharmacy** field as the pharmacy created on the previous section.
4. Yet on **Send RX Notification** step, you may review the generated prescription PDF, and then click on **Send and Stay**
5. At **Notification History** block you should now see the notification contents you just sent.
6. Check your email box.

## Customizing Pharmacy and Patient projects

Pharmacy and patient projects are pretty flexible. It means that you may change, add, remove and rearrange form elements and intruments/steps as much as you can. Every custom field value will be available as wildcard to build PDFs and messages (see [templating section](#templating-pdfs-and-messages)). However, there are a few restrictions that need to be observed in order to keep Send Rx working properly:
- All fields containing the machine name prefix `send_rx_` (such as `send_rx_pharmacy_name`, `send_rx_patient_id`, etc.) should not be changed or deleted.
- On patient project, **Generate & Send prescription** form step should not be changed or removed, and must always remain as the last step.
- On pharmacy project, **Delivery Methods** form step should not be changed or removed.
- On pharmacy project, **Prescribers** form step should not be changed or removed. However, there are no restrictions on managing its subfields (as long as `send_rx_username` field is not changed or removed).

## Templating PDFs and messages
TODO.

## Developer notes

For further customizations, it is possible to extend the sender class:
1. Create an extension of `RxSender` class.
2. Override and customize the methods you need.
3. Declare your new class name on pharmacy project custom settings (by altering the config JSON described at [Creating Patient Project](#creating-patient-project) section).

# Send RX

Send RX is a REDCap extension that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Prerequisites

This project depends on the following libraries to work:
- [mPDF](https://github.com/mpdf/mpdf)
- [REDCap Custom Project Settings Plugin](https://github.com/ctsit/custom_project_settings)
- REDCap Linear Data Entry Workflow

## Installation

### Option 1: With Redcap Deployment tool

If you are deploying the extension using UF CTS-IT's [redcap_deployment](https://github.com/ctsit/redcap_deployment) tools ([https://github.com/ctsit/redcap_deployment](https://github.com/ctsit/redcap_deployment)), you can activate these extensions with those tools as well. If you have an environment named `vagrant` the activation would look like this:

```
fab instance:vagrant activate_hook:redcap_save_record,send_rx_trigger.php
fab instance:vagrant activate_hook:redcap_data_entry_form_top,send_rx_data_entry_form_alter.php
```

### Option 2: Other Environments

The `send_rx_trigger` and `send_rx_data_entry_form_alter` hooks are designed to be activated as `redcap_save_record` and `redcap_data_entry_form_top` hook functions, respectively. The hooks are dependent on a framework that calls _anonymous_ PHP functions such as UF CTS-IT's [Extensible REDCap Hooks](https://github.com/ctsit/extensible-redcap-hooks) ([https://github.com/ctsit/extensible-redcap-hooks](https://github.com/ctsit/extensible-redcap-hooks)). If you are not using such a framework, the hook will need to be edited by changing `return function($project_id)` to `function redcap_every_page_top($project_id)`.

### Developer Notes

When using the local test environment provided by UF CTS-IT's [redcap_deployment](https://github.com/ctsit/redcap_deployment) tools ([https://github.com/ctsit/redcap_deployment](https://github.com/ctsit/redcap_deployment)), you can use the deployment tools to configure the extension for testing in the local VM. If you clone this repo as a child of the `redcap_deployment` repo, you can activate the hook and plugin for testing from the root of the `redcap_deployment` repo like this:

```
fab instance:vagrant test_hook:redcap_save_record/send_rx_trigger.php
fab instance:vagrant test_hook:redcap_data_entry_form_top,send_rx/send_rx_data_entry_form_alter.php
```

## Configuration

In order to activate Send RX extension, we need to create and configure two projects:
- **Pharmacy**: Stores pharmacies and prescribers information.
- **Patient**: Provides the patient/prescription form to the prescriber. Once submitted, the prescription is sent to the pharmacy.

### Step 1: Creating Pharmacy Project
1. Access **+ New Project** page, then import `RXSendPharmacyProjectSample.xml` file.
2. Take note of your new project's ID (you should see it at the URL's `pid` parameter).
3. Go to **Custom Project Settings** of your new project and create a config entry called `send_rx_config` as JSON string, whose keys are described as follows:
- `type`: The project type (`pharmacy` on this case)
- `pdfTemplate`: The PDF prescription markup (e.g. `<div>[patient][administered_drug]: [patient][daily_dosage]</div>`)
- `messageSubject`: The message subject (e.g. `Test prescription`)
- `messageBody`: The message body (e.g. `<div>The prescription file is available at: [pdf_file_url]</div>`)

Thus, the JSON contents should look like this:
```
{"type":"pharmacy","pdfTemplate":"<div>[patient][administered_drug]: [patient][daily_dosage]<\/div>","messageSubject":"Test prescription","messageBody":"<div>The prescription file is available at: [pdf_file_url]<\/div>"}
```

As you might noticed, a few wildcards have been placed on markups above. There is a [full section](#templating-pdfs-and-messages) dedicated to explain PDFs and messages templating, but let's put this aside for a while and move on to the next step.

### Step 2: Creating Patient Project
This is quite analogous to what we just did on previous section.

1. Access **+ New Project** page, then import `RXSendPatientProjectSample.xml` file.
2. Go to **Custom Project Settings** of your new project and create a config entry called `send_rx_config` as a JSON string, whose keys are described as follows:
- `type`: The project type (`patient` on this case)
- `targetProjectId`: The pharmacy project ID we got from the previous step (e.g. `123`)
- _(optional)_ `senderClass`: The PHP class responsible to create prescription PDFs and send messages to the pharmacies. This option opens the possibility of extending the default send engine (`RXSender` class) in order to satisfy all project's needs. If not set, `RXSender` will be used.
- _(optional)_ `sendDefault`: Flag that defines whether the prescription should be sent by default. Defaults to `true`.
- _(optional)_ `lockInstruments`: You might add some edit restrictions to the prescribers once the prescription is done. On this case, you may set a comma separated list of instruments (i.e. form steps names) to be locked after the message is sent (e.g. `lab_orders,prescriptions`).

Thus, the JSON contents should look like this (dont't forget to update `targetProjectId` value):
```
{"type":"patient","targetProjectId":123,"senderClass":"RXSender","sendDefault":true,"lockInstruments":"lab_orders,prescription"}
```

## Sending your First Test Prescription

### Step 1: Create a Pharmacy
1. On pharmacy project, go to **Add / Edit records** and then click on **Add new record**.
2. On first step (**Pharmacy Information**), make sure to set the destination email you want to use in your test.
3. Still on **Pharmacy Information** set **Delivery method** as **Email**.
4. On **Prescribers** step, assuming you are logged as admin, fill **Username** field as `site_admin`.
5. Complete and submit your pharmacy registration.

### Step 2: Create a Prescription and Send it
1. On patient project, go to **Add / Edit records** and then click on **Add new record**
2. Complete all steps until the last step (**Generate & Send prescription**).
3. On **Generate & Send prescription** step, set **Pharmacy** field as the pharmacy created on the previous section.
4. Set your form as complete, make sure **Send prescription on Save** checkbox is checked, then click on **Save & Stay**
5. You should now see the notification contents you just sent at **Notification History** block.
6. Check your email box.

## Customizing Pharmacy and Patient projects

Pharmacy and patient projects are pretty flexible. It means that you may change, add, remove and rearrange form elements and intruments/steps as much as you can. Every custom field value will be available as wildcard to build PDFs and messages (see [templating section](#templating-pdfs-and-messages)). However, there are a few restrictions that need to be observed in order to keep Send RX working properly:
- All fields containing the machine name prefix `send_rx_` (such as `send_rx_pharmacy_name`, `send_rx_patient_id`, etc.) should not be changed or deleted.
- On patient project, **Generate & Send prescription** form step should not be changed or removed, and must always remain as the last step.
- On pharmacy project, **Prescribers** form step should not be changed or removed. However, there are no restrictions on managing its subfields (as long as `send_rx_username` field is not changed or removed).

## Templating PDFs and messages
TODO.

## Further customizations

If the available wildcards provided on the [previous section](#customizing-pdfs-and-messages) are not enough to satisfy your needs, you may extend the sender engine:
1. Create an extension of `RXSender` class.
2. Override and customize the methods you need.
3. Declare your new class name on pharmacy project custom settings (by altering the config JSON described at [Creating Patient Project](#creating-patient-project) section).

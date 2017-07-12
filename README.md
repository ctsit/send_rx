# Send RX

Send RX is a REDCap extension that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Prerequisites

This project depends on [REDCap Custom Project Settings Plugin](https://github.com/ctsit/custom_project_settings) to work.

## Installation

### Option 1: Activating CPS Extension

If you are deploying the extension using UF CTS-IT's [redcap_deployment](https://github.com/ctsit/redcap_deployment) tools ([https://github.com/ctsit/redcap_deployment](https://github.com/ctsit/redcap_deployment)), you can activate these extensions with those tools as well. If you have an environment named `vagrant` the activation would look like this:

```
fab instance:vagrant activate_hook:redcap_save_record,send_rx_trigger.php
fab instance:vagrant activate_hook:redcap_data_entry_form_top,send_rx_data_entry_form_alter.php
```

### Option 2: Deploying the CPS extension in other environments

The hook part of the extension is designed to be activated as `redcap_save_record` and `redcap_data_entry_form_top` hook functions. The hook is dependent on a hook framework that calls _anonymous_ PHP functions such as UF CTS-IT's [Extensible REDCap Hooks](https://github.com/ctsit/extensible-redcap-hooks) ([https://github.com/ctsit/extensible-redcap-hooks](https://github.com/ctsit/extensible-redcap-hooks)). If you are not using such a framework, the hook will need to be edited changing `return function($project_id)` to `function redcap_every_page_top($project_id)`.

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

### Creating Pharmacy Project
1. Access *+ New Project* page, then import `RXSendPharmacyProjectSample.xml` file.
2. Take note of your new project's ID (you should see it at the URL's `pid` parameter).
3. Go to *Custom Project Settings* of your new project and create a config entry called `send_rx_config` as JSON string, whose keys are described as follows:
- `type`: The project type (`pharmacy` on this case)
- `pdfTemplate`: The PDF prescription markup (e.g. `<div>[send_rx_table]</div>`)
- `messageSubject`: The message subject (e.g. `Test prescription`)
- `messageBody`: The message body (e.g. `<div>[send_rx_pdf_url]</div>`)

Thus, the JSON contents should look like this:
```
{"type":"pharmacy","pdfTemplate":"<div>[send_rx_table]<\/div>","messageSubject":"Test prescription","messageBody":"<div>[send_rx_pdf_url]<\/div>"}
```

As you might noticed, a few wildcards have been placed on markups above. There is a [full section](#templating-pdfs-and-messages) dedicated to explain PDFs and messages templating, but let's put this aside for a while and move on to the next step.

### Creating Patient Project
This is quite analogous to what we just did on previous section.

1. Access *+ New Project* page, then import `RXSendPatientProjectSample.xml` file.
2. Go to *Custom Project Settings* of your new project and create a config entry called `send_rx_config` as a JSON string, whose keys are described as follows:
- `type`: The project type (`patient` on this case)
- `targetProjectId`: The pharmacy project ID we got from the previous step (e.g. `123`)
- `senderClass`: The PHP class responsible to create prescription PDFs and send messages to the pharmacies. It must extend abstract class `RXSender` (this project provides a sample class called `SampleRXSender`).
- `sendDefault`: Flag that defines whether the prescription should be sent by default (e.g. `true`)
- `lockInstruments`: You might add some edit restrictions to the prescribers once the prescription is done. On this case, you may set a comma separated list of instruments (i.e. form steps names) to be locked after the message is sent (e.g. `lab_orders,prescriptions`).

Thus, the JSON contents should look like this (dont't forget to update `targetProjectId` value):
```
{"type":"patient","targetProjectId":123,"senderClass":"SampleRXSender","sendDefault":true,"lockInstruments":"lab_orders,prescription"}
```

## Sending your first test prescription

### Creating a pharmacy
1. On pharmacy project, go to *Add / Edit records* and then click on *Add new record*.
2. On first step (*Pharmacy Information*), make sure to set the destination email you want to use in your test.
3. Still on *Pharmacy Information* set *Delivery method* as *Email*.
4. On Prescribers step, assuming you are logged as admin, fill *Username* field as `site_admin`.
5. Complete and submit your pharmacy registration.

### Creating a prescription
1. On patient project, go to *Add / Edit records* and then click on *Add new record*
2. Complete all steps until the last step (*Notification History*).
3. On *Notification* step, set *Pharmacy* field as the one created on the previous section.
4. Set your form as complete, make sure *Send prescription on Save* checkbox is checked, then click on *Save & Stay*
5. You should now see the notification contents you just sent at *Notification History* block.
6. Check your email box.

## Templating PDFs and messages
TODO.

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
- **Pharmacy**: Contains pharmacies and prescribers information.
- **Patient**: Provides the patient/prescription form to the prescriber. Once submitted, the prescription is sent to the pharmacy.

### Creating Pharmacy Project
1. Access *+ New Project* page, then import `RXSendPharmacyProjectSample.xml` file.
2. Keep the project ID of your new project (you should see it at the URL's `pid` parameter).
3. Go to *Custom Project Settings* of your new project and create a config entry called `send_rx_config`, containing a JSON as value, whose keys are described as follows:
- `type`: The project type (on this case, `pharmacy`)
- `pdfTemplate`: The PDF prescription markup (e.g. `<div>[send_rx_table]</div>`)
- `messageSubject`: The message subject (e.g. `Test prescription`)
- `messageBody`: The message body (e.g. `<div>[send_rx_pdf_url]</div>`)

Thus, the JSON contents should look like this:
```
{"type":"pharmacy","pdfTemplate":"<div>[send_rx_table]<\/div>","messageSubject":"Test prescription","messageBody":"<div>[send_rx_pdf_url]<\/div>"}
```

As you might noticed, a few wildcards have been placed on markups above. There is a full section dedicated to explain PDFs and messages templating, but let's put this aside for a while and move on to the next step.

### Creating Patient Project
This is quite analogous to what we just did on previous step.

1. Access *+ New Project* page, then import `RXSendPatientProjectSample.xml` file.
2. Go to *Custom Project Settings* of your new project and create a config entry called `send_rx_config`, containing a JSON as value, whose keys are described as follows:
- `type`: The project type (on this case, `patient`)
- `targetProjectId`: The pharmacy project ID we got from the previous step (e.g. `123`)
- `senderClass`: The PHP class responsible to create prescription PDFs and send messages to the pharmacies. It must extend abstract class `RXSender` (this project provides a sample class called `TestRXSender`).
- `sendDefault`: Flag that defines whether the prescription should be sent by default (e.g. `true`)
- `lockInstruments`: You might add some edit restrictions to the prescribers once the prescription is done. On this case, you may set a comma separated list of instruments (i.e. forms names) to be locked after the message is sent (e.g. ``).

Thus, the JSON contents should look like this:
```
{"type":"patient","targetProjectId":123,"senderClass":"SampleRXSender","sendDefault":true,"lockInstruments":"lab_orders,prescription"}
```

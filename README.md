# Send RX

Send RX is a REDCap extension that allows users to automatically generate prescriptions on PDF format and send them to the pharmacies.

## Getting Started

### Prerequisites

This project depends on [REDCap Custom Project Settings Plugin](https://github.com/ctsit/custom_project_settings) to work.

### Installing

We need to create two projects:
- **Pharmacy**: Contains pharmacies and prescribers information.
- **Patient**: Provides the patient/prescription form to the prescriber. Once submitted, the prescription is sent to the pharmacy.

#### Creating Pharmacy Project
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

#### Creating Patient Project
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

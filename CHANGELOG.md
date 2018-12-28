# Change Log
All notable changes to the Send RX project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [1.7.2] - 2018-12-27
### Changed
- Set send_rx_pdf_is_updated = 1 on successful PDF generation (Philip Chase, tbembersimeao)


## [1.7.1] - 2018-12-21
### Changed
- Adding user account reference after creation. (Tiago Bember Simeao)
- Move authors who no longer work at CTS-IT from config.json to Authors file (Philip Chase)


## [1.7.0] - 2018-12-14
### Added
- Add/improve status messages (Tiago Bember Simeao)
- Add event logging (Tiago Bember Simeao)

### Changed
- Improve DAG assigment validation. (Tiago Bember Simeao)
- Make DAG ID field visible for admins in sites project. (Tiago Bember Simeao)


## [1.6.3] - 2018-11-15
### Changed
- Revert "Preventing PDF links expiration on emails." (Philip Chase)
- Add prescriber email address to WARRIOR PDF template (Philip Chase)


## [1.6.2] - 2018-09-04
### Changed
- Prevent the expiration of PDF links in emails (Tiago Bember Simeao)


## [1.6.1] - 2018-08-09
### Changed
- Revert changes to how PHP libraries are located (Philip Chase)
- Add separate instructions for manual and automated installation of Composer dependencies (Philip Chase)


## [1.6.0] - 2018-08-08
### Added
- Add support for multiple DAGs (integration with DAG Switcher) (Tiago Bember Simeao)

### Changed
- Fix issue by changing constant APP_PATH_DOCROOT with macro __FILE__. (Marly Cormar)


## [1.5.1] - 2018-06-08
### Changed
- Fixing user creation bug under Shib. (Tiago Bember Simeao)
- Fixing DAG add/rename functions header documentation. (Tiago Bember Simeao)
- Fixing bug on sanitation of variables. (Tiago Bember Simeao)
- Improve SQL injection security in the send_rx_rename_dag() and the send_rx_add_dag() methods (Dileep)
- Improving security against SQL injection. (Tiago Bember Simeao)


## [1.5.0] - 2018-05-08
### Changed
- Cleaning up submit buttons on send Rx form. (Tiago Bember Simeao)


## [1.4.0] - 2018-04-23
### Added
- Adding confirmation modal on sending rx operation. (Tiago Bember Simeao)
- Adding option to create users from site staff form. (Tiago Bember Simeao)

### Changed
- Change method for locating composer's vendor folder (Philip Chase)
- Preventing conflicts with other configuration modals. (Tiago Bember Simeao)


## [1.3.2] - 2018-03-05
### Changed
- Remove "Save changes and leave" button on Review & Send form (Tiago Bember Simeao)
- Fixing path reference (control_center.php -> project.php). (Tiago Bember Simeao)
- Fixes on README, unhardcoding module prefix on js. (Tiago Bember Simeao)


## [1.3.1] - 2018-02-08
### Added
- Add compatibility version number to config.json (Marly Cormar)
- Add repo and documentation url to the config.json description (Marly Cormar)
- Protecting non related roles to be affected by permissions rebuild. (Tiago Bember Simeao)

### Changed
- Update minimum version number to 8.0.3 (Marly Cormar)
- Change institution format (Marly Cormar)
- Relabel 'Participant Information' and add DOB (Philip Chase)
- Relabel 'Prescriber' section and move it to the bottom of the RX (Philip Chase)
- Change line endings of WarriorPDFTemplate.html to LF (Philip Chase)
- Swap NPI # for DEA # and widen 'MRN / Pharmacy Patient Identifier' td (Philip Chase)
- Fix date of release for 1.3.0 in CHANGELOG (Philip Chase)


## [1.3.0] - 2018-01-27
### Added
- Bypassing non-crucial instruments to check site completeness (tbembersimeao)


## [1.2.0] - 2018-01-19
### Added
- Allowing only prescribers to send prescriptions. (Tiago Bember Simeao)
- Handling prescriber email field. (Tiago Bember Simeao)

### Changed
- Best practice fix (tbembersimeao)
- Fixing regression (tbembersimeao)


## [1.1.0] - 2017-12-11
### Changed
- Reformat Warrior prescription template to read more like an outline than a table (Philip Chase)
- Improving templates styles. (Tiago Bember Simeao)
- Supporting cross-event Piping on templates. (Tiago Bember Simeao)
- Create Warrior project pdf template as WarriorPDFTemplate.html (Philip Chase)
- Allowing labels with commas on piping data. (Tiago Bember Simeao)
- Handling 'json-array' and 'file' config element types. (Tiago Bember Simeao)
- Converting PDF template file on sample site project from a text field to a file upload field. (Tiago Bember Simeao)
- Unhard-coding patient_id as record ID field. (Tiago Bember Simeao)
- Updating README regarding the new PDF template config field. (Tiago Bember Simeao)
- Allowing sending prescription from an incomplete instrument. (Tiago Bember Simeao)
- Setting PDF template as a config field. (Tiago Bember Simeao)
- Removing restriction of form completeness on Review & Send buttons. (Tiago Bember Simeao)
- Adding missing property on send_rx_get_site_users() function. (Tiago Bember Simeao)
- Adapting Send Rx to work with User Profile module. (Tiago Bember Simeao)


## [1.0.0] - 2017-10-25
### Summary
 - This is the first release

### Added
- Changes to pdf template to accommodate new prescription form (prasadlan)
- Turning Send Rx into a REDCap Module. (Tiago Bember Simeao)
- add helper functions to get user_roles and roles with ids (suryayalla)
- sync the user roles with respect to the newly inputed fields (suryayalla)
- Adapting code to a DAG approach. (Tiago Bember Simeao)
- Processing images and file links to be used on PDF. (Tiago Bember Simeao)
- changes in hook to implement prescribers drop down on first intrument (prasadlan)
- create a utils function which fetches site id based on dag (prasadlan)
- create a method in class to fetch site id based on dag (prasadlan)
- add Designer data class (suryayalla)
- add a method to validate cps config (suryayalla)
- added data entry trigger listener hook to track save form event (prasadlan)
- Adding RXSender class and a few helper functions. (Tiago Bember Simeao)
- Add xml versions of the two projects, sites and patients (Stewart Wehmeyer)
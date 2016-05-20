# DonDominio WeFact Module

## Installation
Download the module .zip and unzip it on your desktop. You will end up with a folder named
`DonDominio`. Upload or copy this folder and its contents to `/path/to/wefact/backoffice/3rdparty/domain/`.

## Activation
Once uploaded to your server, access the WeFact backoffice and go to `Settings > Modules`. If
everything is correct, you will find and entry named `DonDominio Registrar Plugin`. Go to
`Management > Services` and click on `Registrars on the left menu, and then on the `Add Registrar`
button.

Enter the following information:

* **Name:** DonDominio Registrar Plugin
* **Services available for this registrar:** Check only `Domain names`
* **Default domain contacts:** Leave defaults (`Use client information`)
* **Nameservers:** Add your nameservers or leave blank
* **Username:** Your API username
* **Password:** Your API password

Press `Add` when finished to create the registrar.

## Custom fields

You will need to create additional fields in order for the module to properly work.

To create additional fields, go to `Settings > WeFact Preferences` and click on `Own custom fields`
on the left menu. Click on `Add field` and enter the following information:

* **Field name:** The field name on the table below
* **Field code:** The field code on the table below
* **Use for:** Check both `client` and `domain contact`
* **Type:** Select the appropriate value from the table below

At a minimum, you need to create the following field:

| Field Name | Field Code | Field Type |
| ---------- | ---------- | ---------- |
| VAT Number | vatnumber | Text |
| Birth date | dateofbirth | Date |

Depending on the TLDs you want to support, you will need to create the following additional fields:

| TLD | Field name | Field Code | Field Type |
| --- | ---------- | ---------- | ---------- |
| .aero | Aero ID<br>Aero Password | aeroid<br>aeropassword | Text<br>Text |
| .cat<br>.pl<br>.eus<br>.gal | Intended Use | intendeduse | Text |
| .jobs | Registrant Website<br>Admin Website<br>Tech Website<br>Billing Website | ownerwebsite<br>adminwebsite<br>techwebsite<br>billingwebsite | Text<br>Text<br>Text<br>Text |
| .lawyer<br>.attorney<br>.dentist<br>.airforce<br>.army<br>.navy | Contact Info | contactinfo | Text |
| .ltda | Authority<br>License Number | authority<br>license | Text<br>Text |
| .ru | Issuer<br>Issue Date | issuer<br>issuerdate | Text<br>Text |
| .travel | UIN | uin | Text |
| .xxx | Class<br>ID | xxxclass<br>xxxid | One of: `default`, `membership`, `nonResolver`<br>Text |

For more information on required fields, check the [DonDominio API documentation](https://dev.dondominio.com/api/docs/api/#section-5-3).

## More information

More information for the DonDominio WeFact module is available on the [online documentation](https://dev.dondominio.com/wefact/).

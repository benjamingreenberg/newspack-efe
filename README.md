# Newspack EFE Integrator

WordPress plugin for the [EFE news wire service](https://efs.efeservicios.com/).

The plugin retrieves articles from EFE's API in NewsML format, and then saves
them to an XML file that can be imported using a generic/3rd-party WordPress RSS
feed importer Plugin.This is being developed specifically for [Automattic's
Newspack](https://github.com/Automattic/newspack-plugin), though it may work in
non-Newspack WordPress sites.

A paid subscription from EFE is required for the Plugin to work.

**This plugin is in the early stages of development, and should not be used in
production.**

## Setup

After installing and enabling, go to ***Settings - EFE Importer*** to configure
the plugin. Once you have finished setting it up, click ***Save Changes***.

**RSS file to save articles to**: This is the name of the XML file that the
plugin will save articles to.

Example: *efe_articles.xml*

The file will be saved to the site's *wp-content/uploads* directory. It should
end in ".xml", not contain any spaces, or special characters other than hyphens
( - ), underscores ( _ ), or periods ( . ).

### **API Client ID**, **API Client Secret**, and **Product ID**

Your EFE representative will give you the values to put here.

### **Fetch from API**

This checkbox enables fetching from the API, and needs to be checked before you
can test, manually fetch, or enable auto-fetch.

## Usage

### Manual fetching

You can click on the ***Fetch*** button to manually fetch articles. The plugin
will download images, and save or overwrite the file you entered in ***RSS file
to save articles to***.

### Automatic fetching

You can place a checkmark next to ***Auto-fetch every hour*** to have the plugin
automatically fetch articles from EFE once every hour.

## Testing

You can click the ***Test*** button to see if everything is set up correctly.
The plugin will not download images, save or make changes to any files on your
site.

## About the NewsML Standard

NewsML is designed to provide a media-type-independent, structural framework for
multi-media news. Beyond exchanging single items it can also convey packages of
multiple items in a structured layout.

The */samples* directory has a file
with articles returned by the EFE API in the NewsML format (and files with
articles returned in RSS and JSON formats).

Useful links:

- [EFE's NewsML page](https://www.efe.com/documentosefe/efenewsml/EfeNewsML.htm)
- [IPTC's NewsML page (official maintainers)](https://iptc.org/standards/newsml-1/)

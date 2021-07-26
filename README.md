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

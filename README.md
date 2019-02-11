# Islandora XACML Editor

## Introduction

The Islandora XACML Editor provides a graphical user interface to edit XACML
policies for objects in a repository or collection. It adds a new tab to each
collection called Child Policy and a tab to each item called Item Policy,
where permissions can be set on a per User or per Role basis for:

* Object Management: Controls who can set XACML policies.
* Object Viewing: Controls who can view an object.
* Datastreams and MIME types: Controls who can view datastreams by DSID/MIME.

## Requirements

This module requires the following modules/libraries:

* [Islandora](https://github.com/discoverygarden/islandora)
* [Tuque](https://github.com/islandora/tuque)
* [Islandora XACML
  API](https://github.com/discoverygarden/islandora_xacml_editor/tree/8.x/api)

## Installation

Install as
[usual](https://www.drupal.org/docs/8/extending-drupal-8/installing-drupal-8-modules).

## Configuration

### Fedora Configuration

It may be desirable--and in fact necessary for some modules--to disable/remove
one of the default XACML policies which denies any interactions with the POLICY
datastream to users without the "administrator" role.

This policy is located here:
`$FEDORA_HOME/data/fedora-xacml-policies/repository-policies/default/deny-policy-management-if-not-administrator.xml`

### Solr Searching Hook

In order to comply with XACML restrictions placed on objects, a hook is used
to filter results that do not conform to a searching user's roles and name.
This hook will not function correctly if the Solr fields for `ViewableByUser`
and `ViewableByRole` are not defined correctly as they are set in the XSLT.
These values can be set through the admin page for the module.

![image](https://cloud.githubusercontent.com/assets/2371345/9816201/d7e9a1e6-5871-11e5-90a0-51381eaf8fcb.png)

### Drush

#### Apply XACML policy to target object

To add policy.xml to object islandora:57:
`drush -v --user=1 islandora_xacml_editor_apply_policy --policy=/tmp/policy.xml
--pid=islandora:57`

To apply this policy to islandora:57 and all child objects, add the
`--traversal` option.

#### Force XACML inheritance to child objects

To apply the XACML policy from islandora:root to its children:
`drush -v --user=1 islandora_xacml_editor_force_policy_inheritance
--pid=islandora:root`

To apply this policy only to immediate children, use the `--shallow_traversal`
option. Disabled by default

The target object must have a POLICY datastream.

### Notes

The XACML editor hooks into ingesting through the interface. When a child is
added through the interface, the parent's POLICY will be applied if one exists.

If XACML policies are written or edited by hand, it may result in unexpected
behaviour.

## Documentation

Further documentation for this module is available at [our
wiki](https://wiki.duraspace.org/display/ISLANDORA/XACML+Editor).

## Troubleshooting/Issues

Having problems or solved one? Create an issue, check out the Islandora Google
groups.

* [Users](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora)
* [Devs](https://groups.google.com/forum/?hl=en&fromgroups#!forum/islandora-dev)

or contact [discoverygarden](http://support.discoverygarden.ca).

## Maintainers/Sponsors

Current maintainers:

* [discoverygarden](http://www.discoverygarden.ca)

## Development

If you would like to contribute to this module, please check out the helpful
[Documentation](https://github.com/Islandora/islandora/wiki#wiki-documentation-for-developers),
[Developers](http://islandora.ca/developers) section on Islandora.ca and create
an issue, pull request and or contact
[discoverygarden](http://support.discoverygarden.ca).

## License

[GPLv3](http://www.gnu.org/licenses/gpl-3.0.txt)

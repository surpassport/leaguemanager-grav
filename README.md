# League Manager Plugin

The **League Manager Plugin** allows leagues managed on SURPASSPORT [LeagueManager](https://leaguemanager.ie) to be embedded on [Grav CMS](http://github.com/getgrav/grav) powered websites. See [https://leaguemanager.ie/demo](https://leaguemanager.ie/demo) for a live example.


## Installation

Installing the League plugin can be done in one of two ways. The GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

### Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `leaguemanager`. You can find these files on [GitHub](https://github.com/surpassport/leaguemanager-grav) or via [GetGrav.org](http://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/leaguemanager

> NOTE: This plugin is a modular component for Grav which requires [Grav](http://github.com/getgrav/grav) and the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) to operate.

### Admin Plugin

If you use the admin plugin, you can install directly through the admin plugin by browsing the `Plugins` tab and clicking on the `Add` button.

## Configuration

Before configuring this plugin, you should copy the `user/plugins/leaguemanager/leaguemanager.yaml` to `user/config/plugins/leaguemanager.yaml` and only edit that copy.

Here is the default configuration and an explanation of available options:

```yaml
enabled: true
```

Note that if you use the admin plugin, a file with your configuration, and named league.yaml will be saved in the `user/config/plugins/` folder once the configuration is saved in the admin.

## Usage

To make this work on your site, install the **League Manager Plugin** on your Grav website. Next, enable an integration on your account and copy the generated authorisation token into your leaguemanager.yaml file, or use the Admin panel. Done!

Your leagues are listed by season and allows your visitors to view divisions, groupings and fixtures. Results, league tables and playoffs are all presented when available, and can be controlled using the options set on your integration configuration.

For performance reasons, content is cached on your website server for a period of 1 hour. We trust you understand.

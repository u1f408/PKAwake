# PluralKit "Are we awake?" frontend

A simple web client for the PluralKit API, displaying
whether your system has at least one current fronter.

Optionally, displays:

- Member cards for the current fronter(s)
- The length of time since the switch occurred
    - Optionally, only if that time exceeds a configured threshold

You can specify a PluralKit system ID in the URL to
display that system instead of the one configured in
the environment. For example, navigating to
[awake.iris.ac.nz/exmpl](https://awake.iris.ac.nz/exmpl)
will show the PluralKit Example System.

-----

<details>
<summary>Screenshots</summary>

**Awake:** (system has current fronters)

<p align="center" style="text-align:center">

[![Screenshot - awake (system has current fronters)](./img/screenshot-awake.png)](./img/screenshot-awake.png)

</p>

**Asleep:** (system is switched out)

<p align="center" style="text-align:center">

[![Screenshot - asleep (system is switched out)](./img/screenshot-asleep.png)](./img/screenshot-asleep.png)

</p>
</details>

## Installation

The simple version:

0. Clone this repository somewhere your web server can access
0. Run `composer install`
0. Copy `.env.dist` to `.env`
0. Edit `.env` to configure the site
0. Point your web server to the `public/` directory of this repository

### API response caching

If you're running this on a public server,
_please_ configure the built-in caching - 
it reduces the load on the PluralKit servers,
as well as improves page load times:

```shell
% tail -n4 .env
## Should we cache the PluralKit API responses in Redis?
## If `1`, we need a REDIS_URL
SITE_CACHE_ENABLED="1"
REDIS_URL="redis://localhost/0"
```

### Web server setup: apache2

0. Enable `mod_rewrite` and `mod_php`
0. Point the `DocumentRoot` of a virtual host to the
    `public/` directory of this repository

And that's it! `.htaccess` files are magic.

### Web server setup: nginx

```nginx
server {
    listen 443 ssl;
    server_name awake.example.com;
    root /path/to/pkawake/public;

    # Replace this line with however your configuration enables
    # passing `.php` files through to php-fpm
    include fastcgi_php.conf;

    location / {
        try_files $uri /index.php;
    }
}
```

## License

This project is licensed under the terms of
the MIT License, the text of which can be found
in [the LICENSE file](./LICENSE).

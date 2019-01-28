<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/* This is to make sure dockerized website has folder access */
define('FS_METHOD', 'direct');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'N.<_;@DTER&H]!rw7]Yi2b_+xtn/!^_D?<!QSSmdY)`??B ba2ogDm%@xpLSE`Z?');
define('SECURE_AUTH_KEY',  '=&(v`EDD}P5ag3bZ7D*aWK`A (Uq>y|@vfT!z:Q$(/uKF]M#lD(,0glzYyb|`IL<');
define('LOGGED_IN_KEY',    '.kg%r,p8IL!;Bm@AuKjdNC9p:V2_]!4j:9cdD)1`kR*5kHm!922<kX4gb!hEhT[p');
define('NONCE_KEY',        'O|^ED/D1`g36A#hI7fc6J;H1Wg%fCgj%2NbxB-<H7E9 cH7jk//!ULA<OFE.NKTg');
define('AUTH_SALT',        'Y!PLcOClK`=-HCv|sR/lx=V#fN1:..zA8hXsbo?Bw:s?Y6hrI6`a5_r%|nLd$qx&');
define('SECURE_AUTH_SALT', '_%2]n78Ud^OU1}5x}Ldr3_,[c#r3qO4l 2;xeXio?pplBJQzv!mM#+fUFbmr})zW');
define('LOGGED_IN_SALT',   'RjDYFSpS!H*$D,Vl-e.  ;*{eZw,74gqDjPxhCN[i<73=n%Z$i[mf_(IXj^D;#tT');
define('NONCE_SALT',       'I=rcB=U/]h,$;`9#B-9T~wdic1II_2pkU<Z)1z(0^)k~xoAcyy%2WP*bw/Dp3EA~');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

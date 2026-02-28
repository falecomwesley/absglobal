<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'absglobal' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         '+3Ty9StRnAs%m-D}~EZVWDi^6)O|d7P=.r#FH.x)+&m[=4r+1G@1IcQ2oBOq]P9T' );
define( 'SECURE_AUTH_KEY',  'ynPk_:Jle1[by~zbuw^N*$:6Y0Dn=[U_=XZP4AmrlF/aAqtYC<5FnNVp; 2YWuAN' );
define( 'LOGGED_IN_KEY',    'JWA+aP6U8u:8 O`nh_W+m7x,udHTB$?=yT!J;=J995Scw%=<osBMDvkyJBE7!D+D' );
define( 'NONCE_KEY',        'Kmy[=I,PZm?k[V5cdq~#+mOn>O}W~[OTo.&lRmt):2.o[82h& 1+V<ntg<f]b2/y' );
define( 'AUTH_SALT',        'bOKD;>n<^1bmf24i.OW,iRmHY&8jORwrkvZa7U1m6!g9#bei;U8s@~)maz}AqlgR' );
define( 'SECURE_AUTH_SALT', 'jMyCI3m.dj@!lW>&*)kS$j4)N/M:/(BUsxc4*[jKLR?2vcL#bR09ZZ*o;oCQFz^]' );
define( 'LOGGED_IN_SALT',   ')~V cQ=%WSuFIWl.FjNo}~k^eZ?VUn4p<#9IKkSR%!A#~Mym?YGsFZis0<w&U%LK' );
define( 'NONCE_SALT',       '>KxOXDGh{T$`|@<qQlSevpE5msD=7o]fv&4z7?i9/Sp71UCoN?,Asj-+$%T-S!Lu' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', true );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

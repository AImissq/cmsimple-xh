<?php

/**
 * @file seofuncs.php
 *
 * SEO functions.
 *
 * @author    Peter Harteg <peter@harteg.dk>
 * @author    The CMSimple_XH developers <devs@cmsimple-xh.org>
 * @copyright 1999-2009 Peter Harteg
 * @copyright 2009-2020 The CMSimple_XH developers <http://cmsimple-xh.org/?The_Team>
 * @copyright GNU GPLv3 <http://www.gnu.org/licenses/gpl-3.0.en.html>
 */

/**
 * SEO functionality
 *
 * Integration of external plugin with extended functions (optional).
 *
 * Remove empty path segments in an URL
 * Remove $su from FirstPublicPage
 *
 * @return void
 *
 * @since 1.7.3
 */
function XH_URI_Cleaning()
{
    global $cf, $su, $s, $xh_publisher, $pth;

    $parts = parse_url(CMSIMPLE_URL);
    assert(isset($parts['scheme'], $parts['host'], $parts['path']));
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $port = '';
    if (!empty($parts['port'])) {
        $port = ':' . $parts['port'];
    }
    $path = $parts['path'];
    $query_str = '';
    if (isset($_SERVER['QUERY_STRING'])) {
        $query_str = $_SERVER['QUERY_STRING'];
    }

    $redir = false;

    /* Integration of external plugin with extended functions (optional).
     * The plugin must be entered in the configuration of CMSimple_Xh under:
     * seo - name
     * The corresponding PHP file must be named according to this scheme:
     * "%PLUGIN-NAME%Main.php"
     * The corresponding function must be named according to this scheme:
     * "%PLUGIN-NAME%Main"
     *
     * This function is called as follows:
     * "%PLUGIN-NAME%Main"($redir, $scheme, $host, $port, $path, $query_str);
     * $redir (bool), and $scheme, $host, $port, $path, $query_str (all parts of the URI)
     *
     * An array is expected as the return:
     * 'redir'     => bool
     * 'scheme'    => string
     * 'host'      => string
     * 'port'      => string
     * 'path'      => string
     * 'query_str' => string
     */
    $CfExtPluginSEO = trim($cf['seo']['external']);
    if ($CfExtPluginSEO != '') {
        $extPluginPth = $pth['folder']['plugins'] . $CfExtPluginSEO;
        $extPluginPHP = $CfExtPluginSEO . 'Main.php';
        $extPluginFunc = $CfExtPluginSEO . 'Main';
        if (is_readable($extPluginPth . '/' . $extPluginPHP)) {
            include_once($extPluginPth . '/' . $extPluginPHP);
            if (function_exists($extPluginFunc)) {
                $external = $extPluginFunc($redir, $scheme, $host, $port, $path, $query_str);
                $redir = $external['redir'];
                $scheme = $external['scheme'];
                $host = $external['host'];
                $port = $external['port'];
                $path = $external['path'];
                $query_str = $external['query_str'];
            }
        }
    }

    //Remove empty path segments in an URL
    //https://github.com/cmsimple-xh/cmsimple-xh/issues/282
    $ep_count = 0;
    $path = preg_replace(
        '#(/){2,}#s',
        '/',
        $path,
        -1,
        $ep_count
    );
    if ($ep_count > 0) {
        $redir = true;
    }

    //Remove $su from FirstPublicPage
    if (!XH_ADM && $s === $xh_publisher->getFirstPublishedPage()
    && !isset($_GET['login'])
    && !isset($_POST['login'])) {
        $fpp_count = 0;
        $query_str = preg_replace('/^'
                   . preg_quote($su, '/')
                   . '/', '', $query_str, -1, $fpp_count);
        if ($fpp_count > 0) {
            $redir = true;
            header("Cache-Control: no-cache, no-store, must-revalidate");
        }
    }

    //Redirect if adjustments were necessary
    if ($redir) {
        if (isset($_SERVER['PROTOCOL'])
        && !empty($_SERVER['PROTOCOL'])) {
            $protocol = $_SERVER['PROTOCOL'];
        } else {
            $protocol = 'HTTP/1.1';
        }
        $url = $scheme . '://' . $host . $port . $path;
        if ($query_str != '') {
            $url .= '?' . XH_uenc_redir($query_str);
        }
        header("$protocol 301 Moved Permanently");
        header("Location: $url");
        header("Connection: close");
        exit;
    }
}

/**
 * Encode QUERY_STRING for redirect with use uenc()
 *
 * @param string $url_query_str
 * @return string
 **/
function XH_uenc_redir($url_query_str = '')
{
    global $cf;

    $url_sep = $cf['uri']['seperator'];
    $url_query_uencstr = '';

    $url_query_parts = array();
    if (strpos($url_query_str, '&') !== false) {
        $url_query_parts[] = strstr($url_query_str, '&', true);
        $url_query_parts[] = strstr($url_query_str, '&');
    } else {
        $url_query_parts[] = $url_query_str;
    }
    if (strpos($url_query_parts[0], '=') === false) {
        $url_page_array = explode($url_sep, $url_query_parts[0]);
        foreach ($url_page_array as $url_page_tmp) {
            $tmp = uenc($url_page_tmp);
            $tmp = preg_replace('#%(25)*#i', '%', $tmp);
            $url_query_uencstr .= $tmp . $url_sep;
        }
        $url_query_uencstr = rtrim($url_query_uencstr, $url_sep);
    } else {
        $url_query_uencstr = $url_query_parts[0];
    }

    $url_query_uencstr = $url_query_uencstr
                      . (isset($url_query_parts[1]) && $url_query_parts[1] != ''
                            ? $url_query_parts[1]
                            : ''
                        );

    return $url_query_uencstr;
}

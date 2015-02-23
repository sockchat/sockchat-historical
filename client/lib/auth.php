<?php

namespace sockchat;

define("AUTH_FETCH", 1);
define("AUTH_CONFIRM", 2);
define("AUTH_VALIDATE", 3);

define("USER_NORMAL", "0");
define("USER_MODERATOR", "1");

define("LOGS_DISABLED", "0");
define("LOGS_ENABLED", "1");

define("NICK_DISABLED", "0");
define("NICK_ENABLED", "1");

define("CHANNEL_CREATE_DISABLED", "0");
define("CHANNEL_CREATE_TEMP", "1");
define("CHANNEL_CREATE_PERM", "2");

class Auth {
    protected static $args = [];
    protected static $user = [];
    protected static $perms = [[],[]];
    protected static $accept = true;
    public static $out = "";

    public static function GetPageType() {
        return isset($_GET["arg1"]) ? AUTH_CONFIRM : (isset($_GET["uid"]) ? AUTH_VALIDATE : AUTH_FETCH);
    }

    public static function AppendArguments($in) {
        if(!is_array($in)) $in = [$in];
        Auth::$args = array_merge(Auth::$args, $in);
    }

    public static function SetUserData($id, $username, $color) {
        Auth::$user = [$id, $username, $color];
    }

    public static function SetCommonPermissions($rank, $usertype, $viewlogs, $changenick, $createchannel) {
        Auth::$perms[0] = [$rank, $usertype, $viewlogs, $changenick, $createchannel];
    }

    public static function SetCustomPermissions($permarr) {
        Auth::$perms[1] = is_array($permarr) ? $permarr : [$permarr];
    }

    public static function Accept() {
        Auth::$accept = true;
    }

    public static function Deny() {
        Auth::$accept = false;
    }

    public static function Serve() {
        if(Auth::GetPageType() == AUTH_FETCH)
            Auth::$out = Auth::$accept ? "yes\f". implode("\f", Auth::$args) : "no";
        else
            Auth::$out = Auth::$accept ? "yes" . implode("\n", Auth::$user) . "\n" . implode("\f", Auth::$perms[0]) . (Auth::$perms[1] == [] ? "" : "\f". implode("\f", Auth::$perms[1])) : "no";
    }
}
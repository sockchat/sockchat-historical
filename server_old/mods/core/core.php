<?php

namespace sockchat\mods\core;
use \sockchat\mods\GenericMod;

use \sockchat\Context;
use \sockchat\User;
use \sockchat\Utils;
use \sockchat\Message;
use \sockchat\Channel;

class Stack {
    protected $size;
    protected $stack;

    public function __construct($size, $copy = null) {
        $this->size = $size;
        if($copy != null) {
            if($copy->Size() <= $this->size) $this->stack = $copy->Raw();
            else $this->stack = array_slice($copy->Raw(), $copy->Size()-$this->size);
        } else $this->stack = [];
    }

    public function Push($val, $id = -1) {
        if($id == -1)
            array_push($this->stack, $val);
        else
            $this->stack[$id] = $val;
        if(count($this->stack) > $this->size)
            $this->stack = array_slice($this->stack, 1);
    }

    public function Pop() {
        return array_pop($this->stack);
    }

    public function Top() {
        return ($this->stack == []) ? null : $this->stack[count($this->stack)-1];
    }

    public function Bottom() {
        return ($this->stack == []) ? null : $this->stack[0];
    }

    public function MaxSize() {
        return $this->size;
    }

    public function Size() {
        return count($this->stack);
    }

    public function Full() {
        return $this->size == count($this->stack);
    }

    public function Raw() {
        return $this->stack;
    }
}

class Main extends GenericMod {
    protected static $silencedUsers;
    protected static $allowedCmds = ["join", "whois", "afk"];

    protected static $maxAfkTagLength = 5;
    protected static $floodFilterSize = 30;
    protected static $floodFilerDuration = 10;

    protected static $folder;

    protected static $logLength = 300;
    protected static $messageLog;

    public static function Silence($user, $expires) {
        self::$silencedUsers[Utils::$chat["AUTOID"] ? $user->GetOriginalUsername() : $user->id] = $expires;
        $user->SetParameter("silence", $expires);
        $fn = Utils::CreateUniqueFile(self::$folder ."/ffdb/silences");
        file_put_contents($fn, implode("\f", [$expires, $user->id, $user->GetOriginalUsername()]));
    }

    public static function RemoveSilence($user) {
        $search = Utils::$chat["AUTOID"] ? [$user->GetOriginalUsername(), 2] : [$user->id, 1];
        if(array_key_exists($search[0], self::$silencedUsers)) unset(self::$silencedUsers[$search[0]]);
        $user->SetParameter("silence", null);

        $files = glob(self::$folder ."/ffdb/silences/*");
        foreach($files as $file) {
            $parts = explode("\f", file_get_contents($file));
            if($parts[$search[1]] == $search[0]) {
                unlink($file);
                break;
            }
        }
    }

    public static function IsSilencedFromList($user) {
        $key = Utils::$chat["AUTOID"] ? $user->GetOriginalUsername() : $user->id;
        if(array_key_exists($key, self::$silencedUsers)) {
            if(self::$silencedUsers[$key] > gmdate("U") || self::$silencedUsers[$key] == "-1") return self::$silencedUsers[$key];
            else unset(self::$silencedUsers[$key]);
        }

        return 0;
    }

    public static function IsSilenced($user) {
        $tmp = $user->GetParameter("silence");
        if($tmp == null) return false;
        else {
            if($tmp > gmdate("U") || $tmp == "-1") return true;
            else {
                self::RemoveSilence($user);
                return false;
            }
        }
    }

    public static function Init() {
        $dir = self::$folder = self::GetModFolder(__NAMESPACE__);

        if(!file_exists("$dir/ffdb/silences")) mkdir("$dir/ffdb/silences", 0777, true);
        self::$silencedUsers = [];

        $files = glob("$dir/ffdb/silences/*");
        foreach($files as $file) {
            $parts = explode("\f", file_get_contents($file));

            if(gmdate("U") >= $parts[0])
                unlink($file);
            else
                self::$silencedUsers[Utils::$chat["AUTOID"] ? $parts[2] : $parts[1]] = $parts[0];
        }

        self::$messageLog = new Stack(self::$logLength);

        self::AddCommandHook(["join", "create", "close", "pwd", "password", "priv", "privilege", "rank"], "handleChannelCommands");
        self::AddCommandHook(["kick", "ban", "pardon", "unban", "silence", "unsilence", "say", "whois", "ip", "delete", "unbanip", "pardonip", "bans", "banned"], "handleModeratorCommands");
        self::AddCommandHook(["whisper", "msg", "nick", "afk", "me", "action", "who"], "handleUserCommands");
    }

    public static function OnUserJoin($user) {
        if(($time = self::IsSilencedFromList($user)) != 0) $user->SetParameter("silence", $time);
    }

    public static function CheckAFK($user) {
        if(($val = $user->GetParameter("afk")) != null) {
            $user->username = mb_substr($user->username, mb_strlen("&lt;$val&gt;_"));
            $user->SetParameter("afk", null);
            Context::ModifyUser($user);
        }
    }

    public static function OnMessageReceive($user, &$msg) {
        self::CheckAFK($user);
        if(self::IsSilenced($user)) return false;
    }

    public static function OnCommandReceive($user, &$cmd, &$args) {
        self::CheckAFK($user);
        if(self::IsSilenced($user) && !in_array($cmd, self::$allowedCmds)) {
            return false;
        }
    }

    public static function OnPacketReceive($conn, &$pid, &$data) {
        if(($target = Context::GetUserBySock($conn)) != null) {
            if($target->GetParameter("packetlog") == null) $target->SetParameter("packetlog", new Stack(self::$floodFilterSize));
            $target->GetParameter("packetlog")->Push(gmdate("U"));
            $stack = $target->GetParameter("packetlog");
            //var_dump($stack);
            $tmp = new Stack(self::$floodFilterSize-5, $stack);
            if($tmp->Full()) {
                if($stack->Top() - $stack->Bottom() <= self::$floodFilerDuration)
                    Message::PrivateBotMessage(MSG_NORMAL, "flwarn", [], $target);
            }

            if($stack->Full()) {
                if($stack->Top() - $stack->Bottom() <= self::$floodFilerDuration)
                    Context::KickUser($target, null, 0, false, "flood");
            }
        }
    }

    public static function AfterMessageLog($user, $msg, $channel, $flags) {
        self::$messageLog->Push($user->id == -1 ? 999999999 : $user->GetRank(), Message::$msgId);
    }

    public static function handleChannelCommands($cmd, $user, $args) {
        switch($cmd) {
            case "join":
                if(isset($args[0]) && $args[0] != "") {
                    $pwd = isset($args[1]) ? Utils::Hash(implode(" ", array_slice($args, 1))) : "";
                    Context::SwitchChannel($user, $args[0], $pwd);
                }
                break;

            case "create":
                if($user->channelCreationPermission() != "0") {
                    if(isset($args[0]) && $args[0] != "") {
                        $channel = null;
                        if(is_numeric($args[0]) && isset($args[1]) && $args[1] != "") {
                            $args[0] = ($args[0] > $user->getRank()) ? $user->getRank() : $args[0];
                            $channel = new Channel(implode("_", array_slice($args, 1)), "", $args[0], $user);
                        } else {
                            $channel = new Channel(implode("_", $args));
                            $channel->channelOwner = $user;
                        }

                        $channel->channelType = ($user->channelCreationPermission() == 1) ? CHANNEL_TEMP : CHANNEL_PERM;

                        if(($ret = Context::CreateChannel($channel)) == "OK") {
                            if($channel->channelType == CHANNEL_TEMP) Context::SwitchChannel($user, $channel->name, $channel->password);
                            Message::PrivateBotMessage(MSG_NORMAL, "crchan", [$channel->name], $user);
                        } else
                            Message::PrivateUserMessage(Message::$bot, $user, $ret, "1001", false);
                    } else Message::PrivateBotMessage(MSG_ERROR, "cmderr", [], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmdna", ["/create"], $user);
                break;
            case "delete":
                if(isset($args[0]) && $args[0] != "") {
                    $name = implode($args, "_");
                    if(($channel = Context::GetChannel($name)) != null) {
                        if($user->canModerate() || $channel->GetOwner()->id == $user->id) {
                            Context::DeleteChannel($channel);
                            Message::PrivateBotMessage(MSG_NORMAL, "delchan", [$name], $user);
                        } else Message::PrivateBotMessage(MSG_ERROR, "ndchan", [$name], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "nochan", [$name], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmderr", [], $user);
                break;

            case "password":
            case "pwd":
                if($user->canModerate() || Context::GetChannel($user->channel)->GetOwner()->id == $user->id) {
                    Context::ChangeChannelPassword($user->channel, isset($args[0]) ? implode(" ", $args) : "");
                    Message::PrivateBotMessage(MSG_NORMAL, "cpwdchan", [], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmdna", ["/pwd"], $user);
                break;

            case "privilege":
            case "rank":
            case "priv":
                if($user->canModerate() || Context::GetChannel($user->channel)->GetOwner()->id == $user->id) {
                    if(!isset($args[0]) || $args[0] <= $user->getRank()) {
                        Context::ChangeChannelPermission($user->channel, isset($args[0]) ? $args[0] : 0);
                        Message::PrivateBotMessage(MSG_NORMAL, "cprivchan", [], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "rankerr", [], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmdna", ["/priv"], $user);
                break;
        }
    }

    public static function handleModeratorCommands($cmd, $user, $args) {
        if($user->canModerate()) {
            switch ($cmd) {
                case "kick":
                    if(($target = Context::GetUserByName($args[0])) != null) {
                        if($target->getRank() < $user->getRank() && strtolower($args[0]) != strtolower($user->username)) {
                            $length = (!isset($args[1]) || !is_numeric($args[1])) ? 0 : ($args[1] > 0 ? $args[1]*60 : -1);
                            Context::KickUser($target, $user, $length);
                        } else Message::PrivateBotMessage(MSG_ERROR, "kickna", [$args[0]], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                    break;

                case "ban":
                    if(($target = Context::GetUserByName($args[0])) != null) {
                        if($target->getRank() < $user->getRank() && strtolower($args[0]) != strtolower($user->username)) {
                            $length = (!isset($args[1]) || !is_numeric($args[1])) ? -1 : ($args[1] > 0 ? $args[1]*60 : -1);
                            Context::KickUser($target, $user, $length, true);
                        } else Message::PrivateBotMessage(MSG_ERROR, "kickna", [$args[0]], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                    break;

                case "pardon":
                case "unban":
                    if(Context::Unban(null, null, $args[0], $user)) {
                        Message::PrivateBotMessage(MSG_NORMAL, "unban", [$args[0]], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "notban", [$args[0]], $user);
                    break;

                case "pardonip":
                case "unbanip":
                    if(Context::Unban(null, $args[0], null, $user)) {
                        Message::PrivateBotMessage(MSG_NORMAL, "unban", [$args[0]], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "notban", [$args[0]], $user);
                    break;

                case "bans":
                case "banned":
                    $list = [];
                    foreach(Context::$bannedUsers as $bid => $ban) {
                        if($ban->username == null)
                            $push = "<a href=\"javascript:void(0);\" onclick=\"Chat.SendMessageWrapper('/unbanip '+ this.innerHTML);\">". $ban->ip ."</a>";
                        else
                            $push = "<a href=\"javascript:void(0);\" onclick=\"Chat.SendMessageWrapper('/unban '+ this.innerHTML);\">". $ban->username ."</a>";
                        array_push($list, $push);
                    }
                    Message::PrivateBotMessage(MSG_NORMAL, "banlist", [implode(", ", $list)], $user);
                    break;

                case "silence":
                    if(($target = Context::GetUserByName($args[0])) != null) {
                        if($target->id != $user->id) {
                            if($target->getRank() < $user->getRank()) {
                                if (!self::IsSilenced($target)) {
                                    $exp = isset($args[1]) ? (int)gmdate("U") + abs($args[1]*60) : -1;
                                    self::Silence($target, $exp);
                                    Message::PrivateBotMessage(MSG_NORMAL, "silence", [], $target);
                                    Message::PrivateBotMessage(MSG_NORMAL, "silok", [$target->username], $user);
                                } else Message::PrivateBotMessage(MSG_ERROR, "silerr", [], $user);
                            } else Message::PrivateBotMessage(MSG_ERROR, "silperr", [], $user);
                        } else Message::PrivateBotMessage(MSG_ERROR, "silself", [], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                    break;
                case "unsilence":
                    if(($target = Context::GetUserByName($args[0])) != null) {
                        if($target->getRank() < $user->getRank()) {
                            if (self::IsSilenced($target)) {
                                self::RemoveSilence($target);
                                Message::PrivateBotMessage(MSG_NORMAL, "unsil", [], $target);
                                Message::PrivateBotMessage(MSG_NORMAL, "usilok", [$target->username], $user);
                            } else Message::PrivateBotMessage(MSG_ERROR, "usilerr", [], $user);
                        } else Message::PrivateBotMessage(MSG_ERROR, "usilperr", [], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                    break;

                case "say":
                    Message::BroadcastBotMessage(MSG_NORMAL, "say", [implode(" ", $args)]);
                    break;

                case "ip":
                case "whois":
                    if(($tgt = Context::GetUserByName($args[0])) != null) {
                        Message::PrivateBotMessage(MSG_NORMAL, "ipaddr", [$args[0], $tgt->sock->remoteAddress], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                    break;

                case "delete":
                    if(isset($args[0])) {
                        if(array_key_exists($args[0], self::$messageLog->Raw())) {
                            if(self::$messageLog->Raw()[$args[0]] <= $user->GetRank()) {
                                Message::DeleteMessage($args[0]);
                            } else Message::PrivateBotMessage(MSG_ERROR, "delerr", [], $user);
                        } else Message::PrivateBotMessage(MSG_ERROR, "delerr", [], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "cmderr", [], $user);
                    break;
            }
        } else Message::PrivateBotMessage(MSG_ERROR, "cmdna", ["/{$cmd}"], $user);
    }

    public static function handleUserCommands($cmd, $user, $args) {
        switch($cmd) {
            case "nick":
                if($user->canChangeNick()) {
                    $name = "~". trim(Utils::SanitizeName(mb_substr(join("_", $args), 0, Utils::$chat["MAX_USERNAME_LEN"]-1)));
                    if(!isset($args[0])) $name = $user->GetOriginalUsername();
                    if(Context::GetUserByName($name) == null) {
                        Message::BroadcastBotMessage(MSG_NORMAL, "nick", [$user->username, $name], $user->channel);
                        $user->username = $name;
                        Context::ModifyUser($user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "nameinuse", [$name], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmdna", ["/nick"], $user);
                break;
            case "whisper":
            case "msg":
                if(isset($args[0]) && isset($args[1])) {
                    if(($target = Context::GetUserByName($args[0])) != null) {
                        if($target->id != $user->id) {
                            $msg = implode(" ", array_slice($args, 1));
                            Message::PrivateUserMessage($user, $target, $msg);
                            Message::PrivateUserMessage($user, $user, $target->username . " " . $msg);
                        }
                    } else Message::PrivateBotMessage(MSG_ERROR, "usernf", [$args[0]], $user);
                } else Message::PrivateBotMessage(MSG_ERROR, "cmderr", [], $user);
                break;
            case "afk":
                $val = isset($args[0]) ? strtoupper(mb_substr($args[0], 0, self::$maxAfkTagLength)) : "AFK";
                if($user->GetParameter("afk") == null && $val != "") {
                    $user->SetParameter("afk", $val);
                    $user->username = "&lt;$val&gt;_". $user->username;
                    Context::ModifyUser($user);
                }
                break;
            case "action":
            case "me":
                $msg = join(" ", $args);
                if(trim($msg) != "")
                    Message::BroadcastUserMessage($user, "<i>". $msg ."</i>", LOCAL_CHANNEL, "1100");
                break;
            case "who":
                if(isset($args[0])) {
                    if(($chan = Context::GetChannel($args[0])) != null) {
                        if($chan->permissionLevel <= $user->GetRank() && ($chan->password != "" || $user->CanModerate())) {
                            $arr = [];
                            foreach ($chan->users as $u)
                                array_push($arr, "<a href=\"javascript:void(0);\" onclick=\"UI.InsertChatText(this.innerHTML);\"". ($u->id == $user->id ? " style='font-weight: bold;'" : "") .">" . $u->username . "</a>");
                            Message::PrivateBotMessage(MSG_NORMAL, "whochan", [$args[0], implode(", ", $arr)], $user);
                        } else Message::PrivateBotMessage(MSG_ERROR, "whoerr", [$args[0]], $user);
                    } else Message::PrivateBotMessage(MSG_ERROR, "nochan", [$args[0]], $user);
                } else {
                    $arr = [];
                    foreach(Context::$onlineUsers as $u)
                        array_push($arr, "<a href=\"javascript:void(0);\" onclick=\"UI.InsertChatText(this.innerHTML);\"". ($u->id == $user->id ? " style='font-weight: bold;'" : "") .">". $u->username ."</a>");
                    Message::PrivateBotMessage(MSG_NORMAL, "who", [implode(", ", $arr)], $user);
                }
                break;
        }
    }
}
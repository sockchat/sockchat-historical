/// <reference path="ui.ts" />
/// <reference path="msg.ts" />
/// <reference path="user.ts" />
/// <reference path="utils.ts" />
/// <reference path="sound.ts" />
/// <reference path="lang.ts" />
var Socket = (function () {
    function Socket() {
    }
    Socket.Send = function (msg) {
        this.sock.send(msg);
    };
    Socket.Init = function (addr) {
        this.sock = new WebSocket(addr);
        this.sock.onopen = this.onConnOpen;
        this.sock.onmessage = this.onMessageRecv;
        this.sock.onerror = this.onConnError;
        this.sock.onclose = this.onConnClose;
    };
    Socket.ping = function () {
        this.sock.send(Message.Pack(0, "" + UserContext.self.id));
    };
    Socket.onConnOpen = function (e) {
        UI.ChangeDisplay(4);
        setInterval("Socket.ping();", Socket.pingTime * 1000);
        Socket.Send(Message.Pack(1, Message.PackArray(Socket.args)));
    };
    Socket.onMessageRecv = function (e) {
        console.log(e.data);
        var parts = e.data.split(Message.Separator);
        var msgid = +parts[0];
        parts = parts.slice(1);
        switch (msgid) {
            case 1:
                if (UI.currentView == 2) {
                    UI.AddUser(new User(+parts[1], parts[2], parts[3], parts[4]));
                    UI.AddMessage(parts[5], +parts[0], UI.ChatBot, Utils.formatBotMessage("0", "join", [parts[2]]), true, false);
                    Sounds.Play(2 /* Join */);
                }
                else {
                    if (parts[0] == "y") {
                        UserContext.self = new User(+parts[1], parts[2], parts[3], parts[4]);
                        UserContext.self.channel = parts[5];
                        UI.maxMsgLen = +parts[6];
                        UI.ChangeDisplay(2);
                        UI.AddUser(UserContext.self, false);
                    }
                    else
                        alert(UI.langs[UI.currentLang].menuText[7 + +parts[0]] + (+parts[0] == 3 ? " " + (new Date(+parts[1])).toDateString() + "!" : ""));
                }
                break;
            case 2:
                if (+parts[1] != UserContext.self.id) {
                    if (+parts[1] != -1)
                        UI.AddMessage(parts[3], +parts[0], UserContext.users[+parts[1]], parts[2]);
                    else
                        UI.AddMessage(parts[3], +parts[0], UI.ChatBot, parts[2]);
                }
                else
                    UI.AddMessage(parts[3], +parts[0], UserContext.self, parts[2]);
                break;
            case 3:
                UI.AddMessage(parts[4], +parts[3], UI.ChatBot, Utils.formatBotMessage("0", parts[2] == "0" ? "leave" : "kick", [parts[1]]), true, false);
                Sounds.Play(3 /* Leave */);
                UI.RemoveUser(+parts[0]);
                break;
            case 4:
                switch (+parts[0]) {
                    case 0:
                        UI.AddChannel(parts[1], parts[2] == "1", parts[3] == "1");
                        break;
                    case 1:
                        UI.ModifyChannel(parts[1], parts[2], parts[3] == "1", parts[4] == "1");
                        break;
                    case 2:
                        UI.RemoveChannel(parts[1]);
                        break;
                }
                break;
            case 5:
                switch (+parts[0]) {
                    case 0:
                        if (+parts[1] != UserContext.self.id) {
                            UI.AddUser(new User(+parts[1], parts[2], parts[3], parts[4]));
                            UI.AddMessage(parts[5], Utils.UnixNow(), UI.ChatBot, Utils.formatBotMessage("0", "jchan", [parts[2]]), true, false);
                            Sounds.Play(2 /* Join */);
                        }
                        break;
                    case 1:
                        if (+parts[1] != UserContext.self.id) {
                            UI.AddMessage(parts[2], Utils.UnixNow(), UI.ChatBot, Utils.formatBotMessage("0", "lchan", [UserContext.users[+parts[1]].username]), true, false);
                            UI.RemoveUser(+parts[1]);
                            Sounds.Play(3 /* Leave */);
                        }
                        break;
                    case 2:
                        document.getElementById("channeldd").value = parts[1];
                        break;
                }
                break;
            case 6:
                try {
                    var msg = document.getElementById("sock_msg_" + parts[0]);
                    msg.parentElement.removeChild(msg);
                }
                catch (e) {
                }
                break;
            case 7:
                switch (+parts[0]) {
                    case 0:
                        for (var i = 0; i < +parts[1]; i++) {
                            if (+parts[2 + 4 * i] != UserContext.self.id)
                                UI.AddUser(new User(+parts[2 + 4 * i], parts[3 + 4 * i], parts[4 + 4 * i], parts[5 + 4 * i]));
                        }
                        break;
                    case 1:
                        UI.AddMessage(parts[7], +parts[1], (+parts[2] != -1) ? new User(+parts[2], parts[3], parts[4], parts[5]) : UI.ChatBot, parts[6], false, false);
                        break;
                    case 2:
                        for (var i = 0; i < +parts[1]; i++)
                            UI.AddChannel(parts[2 + 3 * i], parts[3 + 3 * i] == "1", parts[4 + 3 * i] == "1");
                        document.getElementById("channeldd").value = UserContext.self.channel;
                        break;
                }
                break;
            case 8:
                if (+parts[0] == 0 || +parts[0] == 3 || +parts[0] == 4) {
                    document.getElementById("chatList").innerHTML = "";
                    UI.rowEven[0] = true;
                }
                if (+parts[0] == 1 || +parts[0] == 3 || +parts[0] == 4) {
                    for (var u in UserContext.users)
                        delete UserContext.users[u];
                    UI.RedrawUserList();
                }
                if (+parts[0] == 2 || +parts[0] == 4) {
                    var tmp = document.getElementById("channeldd");
                    for (var i = tmp.length - 1; i >= 0; i++)
                        tmp.remove(i);
                }
                break;
            case 9:
                alert(+parts[0] == 0 ? UI.langs[UI.currentLang].menuText[5] : UI.langs[UI.currentLang].menuText[6] + " " + (new Date(+parts[1] * 1000)).toDateString() + "!");
                window.location.href = Socket.redirectUrl;
                break;
            case 10:
                if (+parts[0] == UserContext.self.id) {
                    UserContext.self.username = parts[1];
                    UserContext.self.color = parts[2];
                    UserContext.self.permstr = parts[3];
                    UserContext.self.EvaluatePermString();
                    UI.ModifyUser(UserContext.self);
                }
                else {
                    UserContext.users[parts[0]].username = parts[1];
                    UserContext.users[parts[0]].color = parts[2];
                    UserContext.users[parts[0]].permstr = parts[3];
                    UserContext.users[parts[0]].EvaluatePermString();
                    UI.ModifyUser(UserContext.users[parts[0]]);
                }
                break;
        }
    };
    Socket.onConnError = function (e) {
        //alert("errored! error is "+ e.get);
    };
    Socket.onConnClose = function (e) {
        //alert("closed because"+ e.reason);
        UI.ChangeDisplay(3);
        //window.location.href = Socket.redirectUrl;
    };
    return Socket;
})();
//# sourceMappingURL=sock.js.map
/// <reference path="utils.ts" />
var Language = (function () {
    function Language(blob) {
        this.menuText = [];
        this.botText = [];
        this.botErrText = [];
        this.settingsText = [];
        this.helpText = [];
        this.bbCodeText = [];
        var json = [];
        for (var str in blob) {
            if (str == 0)
                this.code = blob[str];
            else
                json.push(JSON.parse(Utils.FetchPage(blob[str] + "?a=" + Utils.Random(1000000000, 9999999999))));
        }
        for (var file in json) {
            if (json[file].name != undefined) {
                this.name = json[file].name;
                this.dir = json[file].dir;
            }
            if (json[file].menuText != undefined) {
                for (var key in json[file].menuText) {
                    this.menuText[key] = json[file].menuText[key];
                }
            }
            if (json[file].botText != undefined) {
                for (var key in json[file].botText) {
                    this.botText[key] = json[file].botText[key];
                }
            }
            if (json[file].botErrText != undefined) {
                for (var key in json[file].botErrText) {
                    this.botErrText[key] = json[file].botErrText[key];
                }
            }
            if (json[file].settingsText != undefined) {
                for (var key in json[file].settingsText) {
                    this.settingsText[key] = json[file].settingsText[key];
                }
            }
            if (json[file].helpText != undefined) {
                for (var key in json[file].helpText) {
                    this.helpText[key] = json[file].helpText[key];
                }
            }
            if (json[file].bbCodeText != undefined) {
                for (var key in json[file].bbCodeText) {
                    this.bbCodeText[key] = json[file].bbCodeText[key];
                }
            }
        }
    }
    Language.prototype.interpretBotString = function (str) {
        var parts = str.split("\f");
        var retval = (parts[0] == "0") ? this.botText[parts[1]] : this.botErrText[parts[1]];
        if (retval == undefined) {
            retval = Utils.replaceAll(Utils.replaceAll(this.botErrText["nolang"], "{0}", parts[1]), "{1}", (parts[0] == "0") ? "message" : "error");
            parts[0] = "1";
        }
        else {
            if (parts[2] != undefined) {
                for (var i = 2; i < parts.length; i++) {
                    retval = Utils.replaceAll(retval, "{" + (i - 2) + "}", parts[i]);
                }
            }
        }
        retval = "<span class='" + (parts[0] == "1" ? "botError" : "botMessage") + "'>" + retval + "</span>";
        return retval;
    };
    Language.prototype.isBotMessageError = function (str) {
        var parts = str.split("\f");
        return (parts[0] == "1") || (((parts[0] == "0") ? this.botText[parts[1]] : this.botErrText[parts[1]]) == undefined);
    };
    return Language;
})();
//# sourceMappingURL=lang.js.map
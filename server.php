<?php
require_once(__DIR__.'/config/config.php');
require_once($cfg['module']['mediawikiurlencode']);

$method = $_SERVER['REQUEST_METHOD'];
if ($method == 'POST') {
	$inputJSON = file_get_contents('php://input');
	$input = json_decode($inputJSON, true);
	$chat_id = $input['message']['chat']['id'];
	if ($cfg['log']) {
		file_put_contents(__DIR__."/data/".$chat_id.".log", $inputJSON);
	}
	$datafile = __DIR__."/data/".$chat_id."_setting.json";
	$data = @file_get_contents($datafile);
	if ($data === false) {
		$data = $cfg['defaultdata'];
	} else if (($data = json_decode($data, true)) === null) {
		$data = $cfg['defaultdata'];
	}
	$data += $cfg['defaultdata'];
	if (isset($input['message']['text']) || isset($input['message']['caption'])) {
		if (isset($input['message']['text'])) {
			$text = $input['message']['text'];
		} else if (isset($input['message']['caption'])) {
			$text = $input['message']['caption'];
		} else {
			$text = "";
		}
		if (strpos($text, "/") === 0) {
			$user_id = $input['message']['from']['id'];
			$res = file_get_contents('https://api.telegram.org/bot'.$cfg['token'].'/getChatMember?chat_id='.$chat_id.'&user_id='.$user_id);
			$res = json_decode($res, true);
			$isadmin = in_array($res["result"]["status"], ["creator", "administrator"]);
			$text = str_replace("\n", " ", $text);
			$text = preg_replace("/^([^ ]+) +/", "$1 ", $text);
			$temp = explode(" ", $text);
			$cmd = $temp[0];
			unset($temp[0]);
			$text = implode(" ", $temp);
			$response = "";
			if ($chat_id < 0 && $cmd === "/cmdadminonly@WikipediaLinkBot") {
				if (!$isadmin) {
					$response = "只有群组管理员可以更改此设置";
				} else {
					$data["cmdadminonly"] = !$data["cmdadminonly"];
					if ($data["cmdadminonly"]) {
						$response = "现在起只有群组管理员可以更改此设置";
					} else {
						$response = "现在起所有人都可以更改回覆设置";
					}
				}
			} else if (($chat_id > 0 && $cmd === "/start") || $cmd === "/start@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					$data["mode"] = "start";
					$response = "已启用链接回覆";
				}
			} else if (($chat_id > 0 && $cmd === "/stop") || $cmd === "/stop@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else if ($chat_id < 0) {
					if ($data["mode"] !== "stop") {
						$data["stoptime"] = time();
					}
					$data["mode"] = "stop";
					$response = "已停用链接回覆";
					if (!in_array($chat_id, $cfg['noautoleavelist'])) {
						$response .= "\nBOT将会在".($cfg['stoplimit']-(time()-$data["stoptime"]))."秒后自动退出";
					}
				} else {
					$data["mode"] = "stop";
					$response = "已停用链接回覆";
				}
			} else if (($chat_id > 0 && $cmd === "/optin") || $cmd === "/optin@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					if ($text === "") {
						$response = "此指令需包含一个参数为正则表达式(php)，当符合这个正则表达式才会回覆链接\n".
						"范例：/optin /pattern/";
					} else {
						if ($text[0] === "/" && substr($text, -1) === "/") {
							$text = substr($text, 1, -1);
						}
						$text = "/".$text."/";
						if (preg_match($text, null) === false) {
							$response = "设置 /optin 的正则表达式包含错误，设置沒有改变";
						} else {
							$data["mode"] = "optin";
							$data["regex"] = $text;
							$response = "已启用部分链接回覆：".$text;
						}
					}
				}
			} else if (($chat_id > 0 && $cmd === "/optout") || $cmd === "/optout@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					if ($text === "") {
						$response = "此指令需包含一个参数为正则表达式(php)，当符合这个正则表达式不会回覆链接\n".
							"范例：/optout /pattern/";
					} else {
						if ($text[0] === "/" && substr($text, -1) === "/") {
							$text = substr($text, 1, -1);
						}
						$text = "/".$text."/";
						if (preg_match($text, null) === false) {
							$response = "设置 /optout 的正则表达式包含错误，设置沒有改变";
						} else {
							$data["mode"] = "optout";
							$data["regex"] = $text;
							$response = "已停用部分链接回覆：".$text;
						}
					}
				}
			} else if (($chat_id > 0 && $cmd === "/settings") || $cmd === "/settings@WikipediaLinkBot") {
				$response = "chat id为".$chat_id.(in_array($chat_id, $cfg['noautoleavelist'])?"（不退出白名单）":"");
				$response .= "\n链接回覆设置为".$data["mode"];
				if (in_array($data["mode"], ["optin", "optout"])) {
					$response .= "\n正则表达式：".$data["regex"]."";
				}
				$response .= "\n页面存在检测为".($data["404"]?"开启":"关闭");
				$response .= "\n链接预览为".($data["pagepreview"]?"开启":"关闭");
				$response .= "\n文章路径为 ".$data["articlepath"];
				if ($chat_id < 0) {
					$response .= "\n".($data["cmdadminonly"]?"只有管理员可以更改回覆设置":"所有人都可以更改回覆设置");
				}
				$response .= "\n使用 /help 查看更改设置的指令";
			} else if (($chat_id > 0 && $cmd === "/404") || $cmd === "/404@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					$data["404"] = !$data["404"];
					if ($data["404"]) {
						$response = "已开启页面存在检测（提醒：回复会稍慢）";
					} else {
						$response = "已关闭页面存在检测";
					}
				}
			} else if (($chat_id > 0 && $cmd === "/pagepreview") || $cmd === "/pagepreview@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改此设置\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					$data["pagepreview"] = !$data["pagepreview"];
					if ($data["pagepreview"]) {
						$response = "已开启链接预览（提醒：仅有一个链接时会预览）";
					} else {
						$response = "已关闭链接预览";
					}
				}
			} else if (($chat_id > 0 && $cmd === "/articlepath") || $cmd === "/articlepath@WikipediaLinkBot") {
				if ($chat_id < 0 && $data["cmdadminonly"] && !$isadmin) {
					$response = "只有群组管理员可以更改文章路径\n群组管理员可使用指令 /cmdadminonly@WikipediaLinkBot 取消此限制";
				} else {
					if ($text === "") {
						$response = "此指令需包含一个参数为文章路径\n".
						"范例：/articlepath https://zh.wikipedia.org/wiki/";
					} else {
						$data["articlepath"] = $text;
						$response = "文章路径已设置为 ".$text;
						$res = file_get_contents($text);
						if ($res === false) {
							$response .= "\n提醒：检测到网页可能不存在";
						}
					}
				}
			} else if (($chat_id > 0 && $cmd === "/help") || $cmd === "/help@WikipediaLinkBot") {
				$response = "/settings 查看链接回覆设置\n".
					"/start 启用所有链接回覆\n".
					"/stop 停用所有链接回覆\n".
					"/optin 启用部分链接回覆(参数设置，使用正则表达式)\n".
					"/optout 停用部分链接回覆(参数设置，使用正则表达式)\n".
					"/404 检测页面存在(开启时回复会较慢)\n".
					"/pagepreview 链接预览(仅有一个链接时会预览)\n".
					"/articlepath 更改文章路径\n";
				if ($chat_id < 0) {
					$response .= "/cmdadminonly 调整是否只有管理员才可更改设置\n";
				}
			} else if (($chat_id > 0 && $cmd === "/editcount") || $cmd === "/editcount@WikipediaLinkBot") {
				$text = trim($text);
				$text = explode("@", $text);
				if (count($text) !== 2 || trim($text[0]) === "" || trim($text[1]) === "") {
					$response = "格式错误，必須为 Username@Wiki";
				} else {
					$text[0] = ucfirst($text[0]);
					$url = "https://xtools.wmflabs.org/ec/".$text[1]."/".urlencode($text[0])."?uselang=en";
					$res = file_get_contents($url);
					if ($res === false) {
						$response = "连接發生错误";
					} else {
						$res = str_replace("\n", "", $res);
						$res = html_entity_decode($res);
						$response = '<a href="'.mediawikiurlencode("https://meta.wikimedia.org/wiki/", "Special:CentralAuth/".$text[0]).'">'.$text[0].'</a>'.
							"@".$text[1].'（<a href="'.$url.'">检查</a>）';
						$get = false;
						file_put_contents(__DIR__."/data/".$text[0].".html", $res);
						if (preg_match("/User groups.*?<\/td>\s*<td>\s*(.*?)\s*<\/td>/", $res, $m)) {
							$response .= "\n权限：".preg_replace("/\s{2,}/", " ", trim($m[1]));
							$get = true;
						}
						if (preg_match("/Global user groups<\/td>\s*<td>\s*(.*?)\s*<\/td>/", $res, $m)) {
							$response .= "\n全域权限：".preg_replace("/\s{2,}/", " ", trim($m[1]));
							$get = true;
						}
						if (preg_match('/(?:<strong>Total edits.*?<\/td>|Total<\/td>)\s*<td.*?>(.*?)<\/td>/', $res, $m)) {
							$response .= "\n总计：".trim(strip_tags($m[1]));
							$get = true;
						}
						if (preg_match('/Live edits<\/td>\s*<td.*?>(.*?)<\/td>/', $res, $m)) {
							$response .= "\n可见编辑：".preg_replace("/\s{2,}/", " ", trim(strip_tags($m[1])));
							$get = true;
						}
						if (preg_match("/<td>Deleted edits<\/td>\s*<td>(.*?)<\/td>/", $res, $m)) {
							$response .= "\n已刪编辑：".preg_replace("/\s{2,}/", " ", trim(strip_tags($m[1])));
							$get = true;
						}
						if (preg_match("/Edits in the past 24 hours<\/td><td>(.+?)<\/td>/", $res, $m)) {
							$response .= "\n24小时內编辑：".trim($m[1]);
							$get = true;
						}
						if (preg_match("/Edits in the past 7 days<\/td><td>(.+?)<\/td>/", $res, $m)) {
							$response .= "\n7天內编辑：".trim($m[1]);
							$get = true;
						}
						if (!$get) {
							$response = '用戶名或Wiki不存在（<a href="'.mediawikiurlencode("https://meta.wikimedia.org/wiki/", "Special:CentralAuth/".$text[0]).'">检查</a>）';
						}
					}
				}
			}
			if ($response !== "") {
				$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/sendMessage -d "chat_id='.$chat_id.'&reply_to_message_id='.$input['message']['message_id'].'&disable_web_page_preview=1&parse_mode=HTML&text='.urlencode($response).'"';
				system($commend);
			}
		} else if ($data["mode"] == "stop") {
			if (!isset($data["stoptime"])) {
				$data["stoptime"] = time();
			}
			if (time() - $data["stoptime"] > $cfg['stoplimit'] && !in_array($chat_id, $cfg['noautoleavelist'])) {
				$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/sendMessage -d "chat_id='.$chat_id.'&text='.urlencode("因为停用回覆过久，BOT将自动退出以节约服务器资源，欲再使用请重新加入BOT").'"';
				system($commend);
				$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/leaveChat -d "chat_id='.$chat_id.'"';
				system($commend);
			}
		} else if ($data["mode"] == "optin" && !preg_match($data["regex"], $text)) {
			
		} else if ($data["mode"] == "optout" && preg_match($data["regex"], $text)) {

		} else if (preg_match_all("/(\[\[([^\[\]])+?]]|{{([^{}]+?)}})/", $text, $m)) {
			$data["lastuse"] = time();
			$response = [];
			foreach ($m[1] as $temp) {
				$articlepath = $data["articlepath"];
				if (preg_match("/^\[\[([^|#]+)(?:#([^|]+))?.*?]]$/", $temp, $m2)) {
					$prefix = "";
					$page = trim($m2[1]);
					if (preg_match("/^:?moe:(.*)$/i", $page, $m3)) {
						$articlepath = "https://zh.moegirl.org/";
						$page = $m3[1];
					} else if (preg_match("/^:?kom?:(.*)$/i", $page, $m3)) {
						$articlepath = "https://wiki.komica.org/";
						$page = $m3[1];
					} else if (preg_match("/^:?unct?:(.*)$/i", $page, $m3)) {
						$articlepath = "http://uncyclopedia.tw/wiki/";
						$page = $m3[1];
					} else if (preg_match("/^:?uncc:(.*)$/i", $page, $m3)) {
						$articlepath = "http://cn.uncyclopedia.wikia.com/wiki/";
						$page = $m3[1];
					} else if (preg_match("/^:?abf:(.*)$/i", $page, $m3)) {
						$articlepath = "https://zh.wikipedia.org/wiki/Special:AbuseFilter/";
						$page = $m3[1];
					} else if (preg_match("/^:?(?:cpb|ctext):(.*)$/i", $page, $m3)) {
						$articlepath = "http://ctext.org/dictionary.pl?if=gb&char=";
						$page = $m3[1];
					} else if (preg_match("/^:?(?:cpba|ctexta):(.*)$/i", $page, $m3)) {
						$articlepath = "http://ctext.org/searchbooks.pl?if=gb&author=";
						$page = $m3[1];
					} else if (preg_match("/^:?mc:(.*)$/i", $page, $m3)) {
						$articlepath = "https://minecraft-zh.gamepedia.com/";
						$page = $m3[1];
					}
					$page = preg_replace("/^Special:AF/i", "Special:AbuseFilter", $page);
					$page = preg_replace("/:$/i", "%3A", $page);
					$page = preg_replace("/!$/i", "%21", $page);
					$page = preg_replace("/\?$/i", "%3F", $page);
					if (isset($m2[2])) {
						$section = $m2[2];
					} else {
						$section = "";
					}
				} else if (preg_match("/^{{ *#(exer|if|ifeq|ifexist|ifexpr|switch|time|language|babel|invoke) *:/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:解析器函数";
					$section = $m2[1];
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:CURRENTYEAR|CURRENTMONTH|CURRENTMONTHNAME|CURRENTMONTHNAMEGEN|CURRENTMONTHABBREV|CURRENTDAY|CURRENTDAY2|CURRENTDOW|CURRENTDAYNAME|CURRENTTIME|CURRENTHOUR|CURRENTWEEK|CURRENTTIMESTAMP|LOCALYEAR|LOCALMONTH|LOCALMONTHNAME|LOCALMONTHNAMEGEN|LOCALMONTHABBREV|LOCALDAY|LOCALDAY2|LOCALDOW|LOCALDAYNAME|LOCALTIME|LOCALHOUR|LOCALWEEK|LOCALTIMESTAMP) .*}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "日期与时间";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:SITENAME|SERVER|SERVERNAME|DIRMARK|DIRECTIONMARK|SCRIPTPATH|CURRENTVERSION|CONTENTLANGUAGE|CONTENTLANG) .*}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "技术元数据";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:REVISIONID|REVISIONDAY|REVISIONDAY2|REVISIONMONTH|REVISIONYEAR|REVISIONTIMESTAMP|REVISIONUSER|PAGESIZE|PROTECTIONLEVEL|DISPLAYTITLE|DEFAULTSORT|DEFAULTSORTKEY|DEFAULTCATEGORYSORT)(:.+?)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "技术元数据";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:NUMBEROFPAGES|NUMBEROFARTICLES|NUMBEROFFILES|NUMBEROFEDITS|NUMBEROFVIEWS|NUMBEROFUSERS|NUMBEROFADMINS|NUMBEROFACTIVEUSERS|PAGESINCATEGORY|PAGESINCAT|PAGESINCATEGORY|PAGESINCATEGORY|PAGESINCATEGORY|PAGESINCATEGORY|NUMBERINGROUP|NUMBERINGROUP|PAGESINNS|PAGESINNAMESPACE)([:|].+?)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "统计";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:FULLPAGENAME|PAGENAME|BASEPAGENAME|SUBPAGENAME|SUBJECTPAGENAME|TALKPAGENAME|FULLPAGENAMEE|PAGENAMEE|BASEPAGENAMEE|SUBPAGENAMEE|SUBJECTPAGENAMEE|TALKPAGENAMEE)(:.+?)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "页面标题";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:NAMESPACE|SUBJECTSPACE|ARTICLESPACE|TALKSPACE|NAMESPACEE|SUBJECTSPACEE|TALKSPACEE)(:.+?)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "命名空间";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(?:NAMESPACE|SUBJECTSPACE|ARTICLESPACE|TALKSPACE|NAMESPACEE|SUBJECTSPACEE|TALKSPACEE)(:.+?)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "命名空间";
				} else if (preg_match("/^{{ *! *}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "其他";
				} else if (preg_match("/^{{ *(localurl|fullurl|filepath|urlencode|anchorencode):.+}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "URL数据";
				} else if (preg_match("/^{{ *(localurl|fullurl|filepath|urlencode|anchorencode):.+}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "命名空间_2";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?(lc|lcfirst|uc|ucfirst|formatnum|#dateformat|#formatdate|padleft|padright|plural):.+}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "格式";
				} else if (preg_match("/^{{ *(int|#special|#tag|gender|PAGEID|noexternallanglinks)(:.+)?}}$/", $temp, $m2)) {
					$prefix = "";
					$page = "Help:魔术字";
					$section = "杂项";
				} else if (preg_match("/^{{ *(?:subst:|safesubst:)?([^|]+)(?:|.+)?}}$/", $temp, $m2)) {
					$prefix = "Template:";
					$page = trim($m2[1]);
					$section = "";
				} else {
					continue;
				}
				$url = mediawikiurlencode($articlepath, $prefix.$page, $section);
				$text = $url;
				if ($data["404"]) {
					$res = @file_get_contents($url);
					if ($res === false) {
						$text .= " （404，<a href='".$articlepath."Special:Search?search=".urlencode($page)."&fulltext=1'>搜尋</a>）";
					}
				}
				$response[]= $text;
			}
			$responsetext = implode("\n", $response);
			$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/sendMessage -d "'.
				'chat_id='.$chat_id.'&'.
				'reply_to_message_id='.$input['message']['message_id'].'&'.
				(count($response)>1||!$data["pagepreview"] ? 'disable_web_page_preview=1&' : '').
				'parse_mode=HTML&'.
				'text='.urlencode($responsetext).'"';
			system($commend);
		} else {
			if (time() - $data["lastuse"] > $cfg['unusedlimit'] && !in_array($chat_id, $cfg['noautoleavelist'])) {
				$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/sendMessage -d "chat_id='.$chat_id.'&text='.urlencode("BOT發現已經".$cfg['unusedlimit']."秒沒有被使用了，因此将自动退出以节约服务器资源，欲再使用请重新加入BOT").'"';
				system($commend);
				$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/leaveChat -d "chat_id='.$chat_id.'"';
				system($commend);
			}
		}
		file_put_contents($datafile, json_encode($data));
	} else if (isset($input['message']['new_chat_member'])) {
		if ($input['message']['new_chat_member']['username'] == $cfg['bot_username']) {
			$data["lastuse"] = time();
			$data["stoptime"] = time();
			$commend = 'curl https://api.telegram.org/bot'.$cfg['token'].'/sendMessage -d "chat_id='.$chat_id.'&text='.urlencode("感谢您使用本BOT，当输入[[页面名]]或{{模板名}}时，BOT将会自动回覆链接").'"';
			system($commend);
			file_put_contents($datafile, json_encode($data));
		}
	}
}

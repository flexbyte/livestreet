<?php
/**
 * OpenСomments - плагин для гостевых комментариев
 *
 * Автор:	flexbyte 
 * Профиль:	http://livestreet.ru/profile/flexbyte/
 * Сайт:	http://flexbyte.com
 **/

// Позволяет вкл\выкл добавление анонимных комментариев
// без отключения плагина, т.к. при отключенном плагине 
// вместо имени гостя будет отображаться guest 
Config::Set('opencomments.enabled', true);

// Запрашивать e-mail
Config::Set('opencomments.ask_mail', true);

return $config;
?>
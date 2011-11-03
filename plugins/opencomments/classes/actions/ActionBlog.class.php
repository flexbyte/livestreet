<?php
/**
 * OpenСomments - плагин для гостевых комментариев
 *
 * Автор:	flexbyte 
 * Профиль:	http://livestreet.ru/profile/flexbyte/
 * Сайт:	http://flexbyte.com
 **/

/**
 * Класс обработки URL'ов вида /blog/
 *
 */

class PluginOpencomments_ActionBlog extends PluginOpencomments_Inherit_ActionBlog 
{ 
    
	/**
	 * Обработка добавление комментария к топику
	 *	 
	 * @return bool
	 */
	protected function SubmitComment() {

		/**
		 * Проверяем авторизован ли пользователь
		 */
		$guest = false;
		if (!$this->User_IsAuthorization()) {
			$this->oUserCurrent = $this->User_GetUserById(0);
			$guest = true;

			if (!Config::Get('opencomments.enabled')) {
				$this->Message_AddErrorSingle($this->Lang_Get('not_access'),$this->Lang_Get('error'));
				return;
			}
			
			/**
			* Проверяем на наличие aceAdminPanel, чтобы проверить IP адрес в бане
			*/
			if (class_exists('PluginAceadminpanel')) {
				$plugins = $this->Plugin_GetActivePlugins();
				if (in_array('aceadminpanel', $plugins)) {
					if ($this->PluginAceadminpanel_Admin_IsBanIp(func_getIp())) {
						$this->Message_AddErrorSingle($this->Lang_Get('adm_banned2_text'), $this->Lang_Get('error'));
						return;
					}
				}
			}
			
            if (!func_check(getRequest("guest_name"),"text",2,20)) {
                $this->Message_AddErrorSingle($this->Lang_Get('opencomments_error_name'),$this->Lang_Get('error'));
                return;
            }
            
			if (Config::Get('opencomments.ask_mail')) {
				if (!func_check(getRequest("guest_email"),"mail")) {
					$this->Message_AddErrorSingle($this->Lang_Get('opencomments_error_mail'),$this->Lang_Get('error'));
					return;
				}
			}

            if (!isset($_SESSION['captcha_keystring']) or $_SESSION['captcha_keystring']!=strtolower(getRequest('captcha'))) {
                $this->Message_AddErrorSingle($this->Lang_Get('opencomments_error_captcha'),$this->Lang_Get('error'));
                return;
            }
		}

		/**
		 * Проверяем топик
		 */
		if (!($oTopic=$this->Topic_GetTopicById(getRequest('cmt_target_id')))) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		/**
		 * Возможность постить коммент в топик в черновиках
		 */
		if (!$oTopic->getPublish() and $this->oUserCurrent->getId()!=$oTopic->getUserId() and !$this->oUserCurrent->isAdministrator()) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}

		/**
		* Проверяем разрешено ли постить комменты
		*/
		if (!$guest and !$this->ACL_CanPostComment($this->oUserCurrent) and !$this->oUserCurrent->isAdministrator()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_acl'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем разрешено ли постить комменты по времени
		*/
		if (!$guest and !$this->ACL_CanPostCommentTime($this->oUserCurrent) and !$this->oUserCurrent->isAdministrator()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_limit'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем запрет на добавления коммента автором топика
		*/
		if ($oTopic->getForbidComment()) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_notallow'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверяем текст комментария
		*/
		if ($guest) {
			$sText=nl2br(strip_tags(getRequest('comment_text')));
		} else {
			$sText=$this->Text_Parser(getRequest('comment_text'));
		}
		
		if (!func_check($sText,'text',2,10000)) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_add_text_error'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Проверям на какой коммент отвечаем
		*/
		$sParentId=(int)getRequest('reply');
		if (!func_check($sParentId,'id')) {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
			return;
		}
		$oCommentParent=null;
		if ($sParentId!=0) {
			/**
			* Проверяем существует ли комментарий на который отвечаем
			*/
			if (!($oCommentParent=$this->Comment_GetCommentById($sParentId))) {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
			/**
			* Проверяем из одного топика ли новый коммент и тот на который отвечаем
			*/
			if ($oCommentParent->getTargetId()!=$oTopic->getId()) {
				$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
				return;
			}
		} else {
			/**
			* Корневой комментарий
			*/
			$sParentId=null;
		}
		/**
		* Проверка на дублирующий коммент
		*/
		if ($this->Comment_GetCommentUnique($oTopic->getId(),'topic',$this->oUserCurrent->getId(),$sParentId,md5($sText))) {
			$this->Message_AddErrorSingle($this->Lang_Get('topic_comment_spam'),$this->Lang_Get('error'));
			return;
		}
		/**
		* Создаём коммент
		*/
		$oCommentNew=Engine::GetEntity('Comment');
		$oCommentNew->setTargetId($oTopic->getId());
		$oCommentNew->setTargetType('topic');
		$oCommentNew->setTargetParentId($oTopic->getBlog()->getId());
		$oCommentNew->setUserId($this->oUserCurrent->getId());
		$oCommentNew->setText($sText);
		$oCommentNew->setDate(date("Y-m-d H:i:s"));
		$oCommentNew->setUserIp(func_getIp());
		$oCommentNew->setPid($sParentId);
		$oCommentNew->setTextHash(md5($sText));
		$oCommentNew->setPublish($oTopic->getPublish());
		
		if ($guest) { 
            $oCommentNew->setGuestName(getRequest("guest_name"));                           
            $oCommentNew->setGuestEmail(getRequest("guest_email"));                                
            unset($_SESSION['captcha_keystring']);
        } else {
		    $oCommentNew->setGuestName(null);                           
            $oCommentNew->setGuestEmail(null);
		}

		/**
		* Добавляем коммент
		*/
		$this->Hook_Run('comment_add_before', array('oCommentNew'=>$oCommentNew,'oCommentParent'=>$oCommentParent,'oTopic'=>$oTopic));
		if ($this->Comment_AddComment($oCommentNew)) {
			$this->Hook_Run('comment_add_after', array('oCommentNew'=>$oCommentNew,'oCommentParent'=>$oCommentParent,'oTopic'=>$oTopic));

			$this->Viewer_AssignAjax('sCommentId',$oCommentNew->getId());
			if ($oTopic->getPublish()) {
				/**
			 	* Добавляем коммент в прямой эфир если топик не в черновиках
			 	*/
				$oCommentOnline=Engine::GetEntity('Comment_CommentOnline');
				$oCommentOnline->setTargetId($oCommentNew->getTargetId());
				$oCommentOnline->setTargetType($oCommentNew->getTargetType());
				$oCommentOnline->setTargetParentId($oCommentNew->getTargetParentId());
				$oCommentOnline->setCommentId($oCommentNew->getId());

				$this->Comment_AddCommentOnline($oCommentOnline);
			}
			/**
			* Сохраняем дату последнего коммента для юзера
			*/
			$this->oUserCurrent->setDateCommentLast(date("Y-m-d H:i:s"));
			$this->User_Update($this->oUserCurrent);
			/**
			* Отправка уведомления автору топика
			*/
			$oUserTopic=$oTopic->getUser();
			if ($oCommentNew->getUserId()!=$oUserTopic->getId()) {
				$this->Notify_SendCommentNewToAuthorTopic($oUserTopic,$oTopic,$oCommentNew,$this->oUserCurrent);
			}
			/**
			* Отправляем уведомление тому на чей коммент ответили
			*/
			if ($oCommentParent and $oCommentParent->getUserId()!=$oTopic->getUserId() and $oCommentNew->getUserId()!=$oCommentParent->getUserId()) {
				$oUserAuthorComment=$oCommentParent->getUser();
				$this->Notify_SendCommentReplyToAuthorParentComment($oUserAuthorComment,$oTopic,$oCommentNew,$this->oUserCurrent);
			}

            /**
             * Добавляем событие в ленту
             */
            $this->Stream_write($oCommentNew->getUserId(), 'add_comment', $oCommentNew->getId(), $oTopic->getPublish() && $oTopic->getBlog()->getType()!='close');
		} else {
			$this->Message_AddErrorSingle($this->Lang_Get('system_error'),$this->Lang_Get('error'));
		}
	}

	/**
	 * Получение новых комментариев
	 *
	 */
	protected function AjaxResponseComment() {
		if (!$this->oUserCurrent) {
			$this->oUserCurrent = $this->User_GetUserById(0);
		}
		parent::AjaxResponseComment();
	}	
}
?>
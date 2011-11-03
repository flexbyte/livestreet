<?php
/**
 * OpenСomments - плагин для гостевых комментариев
 *
 * Автор:	flexbyte 
 * Профиль:	http://livestreet.ru/profile/flexbyte/
 * Сайт:	http://flexbyte.com
 **/

/**
 * Обрабатывает RSS
 * Автор класса vovazol(http://livestreet.ru/profile/vovazol/)
 *
 */
class PluginOpencomments_ActionRss extends PluginOpencomments_Inherit_ActionRss {


	protected function RssComments() {
		/**
		 * Вычисляем топики из закрытых блогов, чтобы исключить их из выдачи
		 */
		$aCloseTopics = $this->Topic_GetTopicsCloseByUser();		
		
		$aResult=$this->Comment_GetCommentsAll('topic',1,Config::Get('module.comment.per_page')*2,$aCloseTopics);
		$aComments=$aResult['collection'];
		
		$aChannel['title']=Config::Get('view.name');
		$aChannel['link']=Config::Get('path.root.web');
		$aChannel['description']=Config::Get('path.root.web').' / RSS channel';
		$aChannel['language']='ru';
		$aChannel['managingEditor']=Config::Get('general.rss_editor_mail');
		$aChannel['generator']=Config::Get('path.root.web');
		
		$comments=array();
		foreach ($aComments as $oComment){
			$item['title']='Comments: '.$oComment->getTarget()->getTitle();
			$item['guid']=$oComment->getTarget()->getUrl().'#comment'.$oComment->getId();
			$item['link']=$oComment->getTarget()->getUrl().'#comment'.$oComment->getId();
			$item['description']=$oComment->getText();
			$item['pubDate']=$oComment->getDate();
			
			if ($oComment->getUserId())
				$item['author']=$oComment->getUser()->getLogin();
			else
				$item['author']=$oComment->getGuestName();
			$item['category']='comments';
			$comments[]=$item;
		}
		
		$this->InitRss();
		$this->Viewer_Assign('aChannel',$aChannel);
		$this->Viewer_Assign('aItems',$comments);
		$this->SetTemplateAction('index');
	}

	protected function RssTopicComments() {
		$sTopicId=$this->GetParam(0);
		
		if (!($oTopic=$this->Topic_GetTopicById($sTopicId)) or !$oTopic->getPublish() or $oTopic->getBlog()->getType()=='close') {
			return parent::EventNotFound();
		}
		
		$aComments=$this->Comment_GetCommentsByTargetId($oTopic->getId(),'topic');
		$aComments=$aComments['comments'];
		
		$aChannel['title']=Config::Get('view.name');
		$aChannel['link']=Config::Get('path.root.web');
		$aChannel['description']=Config::Get('path.root.web').' / RSS channel';
		$aChannel['language']='ru';
		$aChannel['managingEditor']=Config::Get('general.rss_editor_mail');
		$aChannel['generator']=Config::Get('path.root.web');
		
		$comments=array();
		foreach ($aComments as $oComment){
			$item['title']='Comments: '.$oTopic->getTitle();
			$item['guid']=$oTopic->getUrl().'#comment'.$oComment->getId();
			$item['link']=$oTopic->getUrl().'#comment'.$oComment->getId();
			$item['description']=$oComment->getText();
			$item['pubDate']=$oComment->getDate();
			
			if ($oComment->getUserId())
				$item['author']=$oComment->getUser()->getLogin();
			else
				$item['author']=$oComment->getGuestName();
			$item['category']='comments';
			$comments[]=$item;
		}
		
		$this->InitRss();
		$this->Viewer_Assign('aChannel',$aChannel);
		$this->Viewer_Assign('aItems',$comments);
		$this->SetTemplateAction('index');	
	}
	
}
?>
<?php
if(!defined('RX_VERSION'))
{
	exit;
}
if($called_position !== 'before_module_init' || Context::get('logged_info')->is_admin === 'Y' || !Context::get('nick_name'))
{
	return;
}

require_once('addons/required_nickname/class.php');
getController('module')->addTriggerFunction('moduleObject.proc', 'before', function($oModule) use($addon_info)
{
	if(!preg_match('/^(?:procMember(?:Insert|ModifyInfo)|procItemshopApplyItem)$/', $oModule->act, $matches))
	{
		return;
	}
	
	new addons\required_nickname($addon_info);
});

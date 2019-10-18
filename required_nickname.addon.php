<?php
if(!defined('RX_VERSION'))
{
	exit;
}
if($called_position !== 'before_module_init')
{
	return;
}
if(!Context::get('nick_name') || Context::get('is_logged') && Context::get('logged_info')->is_admin === 'Y')
{
	return;
}

getController('module')->addTriggerFunction('moduleObject.proc', 'before', function($oModule) use($addon_info)
{
	if(!preg_match('/^(?:procMember(?:Insert|ModifyInfo)|procItemshopApplyItem)$/', $oModule->act, $matches))
	{
		return;
	}
	require_once('addons/required_nickname/class.php');
	new Addons\required_nickname($addon_info);
});

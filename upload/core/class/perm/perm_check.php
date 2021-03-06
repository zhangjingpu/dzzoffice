<?php
/*
 * @copyright   Leyun internet Technology(Shanghai)Co.,Ltd
 * @license     http://www.dzzoffice.com/licenses/license.txt
 * @package     DzzOffice
 * @link        http://www.dzzoffice.com
 * @author      zyx(zyx@dzz.cc)
 */

class perm_check{  
    
  	function getuserPerm(){
		global $_G;
		$perm= DB::result_first("select perm from %t where uid=%d",array('user_field',$_G['uid']));
		$groupperm=$_G['group']['perm'];
		if($perm<1) $perm=intval($_G['group']['perm']);
		if($_G['setting']['allowshare']){
			$power=new perm_binPerm($perm);
			$perm=$power->delPower('share');
		}
		return $perm;
	}
	function getPerm($fid, $bz='',$i=0){
		global $_G;
		///*static $i=0;
		$i++;
		if($i>20){ //防死循环，如果循环20次以上，直接退出；
			return false;
		}
		
		if($folder=C::t('folder')->fetch($fid)){
			$perm=intval($folder['perm']);
		    $power=new perm_binPerm($perm);
			if($folder['gid']){
				if(C::t('organization_admin')->ismoderator_by_uid_orgid($folder['gid'],$_G['uid'])) return perm_binPerm::getGroupPower('all');
				
				if($power->isPower('flag')){//不继承，使用此权限
					if($_G['setting']['allowshare']){
						$perm=$power->delPower('share');
					}
					$power1=new perm_binPerm($perm);
					return $power1->mergePower(self::getuserPerm());
				}else{ //继承上级，查找上级
					if($folder['pfid']>0 && $folder['pfid']!=$folder['fid']){ //有上级目录
						//return self::getPerm($folder['pfid'],$bz,$i);
						$perm=self::getPerm($folder['pfid'],$bz,$i);
						$power1=new perm_binPerm($perm);
						return $power1->mergePower(self::getuserPerm());
					}else{   //其他的情况使用
						return perm_binPerm::getGroupPower('read');
					}
				}
			}else{
				if($power->isPower('flag')){//不继承，使用此权限
					if($_G['setting']['allowshare']){
						$perm=$power->delPower('share');
					}
					$power1=new perm_binPerm($perm);
					return $power1->mergePower(self::getuserPerm());;
				}else{ //继承上级，查找上级
					/*if($folder['pfid']>0 && $folder['pfid']!=$folder['fid']){ //有上级目录
						return self::getPerm($folder['pfid'],$bz,$i);
					}else{   //其他的情况使用*/
						return self::getuserPerm();
					//}
				}
			}
		}else{
			return 3;
		}
	}
	function getPerm1($fid, $bz='',$i=0){
		global $_G;
		
		$i++;
		if($i>20){ //防死循环，如果循环20次以上，直接退出；
			return false;
		}
		if($folder=C::t('folder')->fetch($fid)){
			if($folder['gid']){
				$perm=intval($folder['perm']);
				$power=new perm_binPerm($perm);
				if($power->isPower('flag')){//不继承，使用此权限
					return $perm;
				}else{ //继承上级，查找上级
					if($folder['pfid']>0 && $folder['pfid']!=$folder['fid']){ //有上级目录
						return self::getPerm1($folder['pfid'],$bz,$i);
					}else{   //其他的情况使用
						return perm_binPerm::getGroupPower('read');;
					}
				}
			}else{
				return self::getuserPerm();
			}
		}else{
			return 3;
		}
	}
	
	function userPerm($fid,$action){ //判断容器有没有指定的权限
		global $_G;
		if($_G['adminid']==1){ //是管理员
			return true;
		}
		
		if(!$_G['uid']){ //如果不是登录用户，返回false;
			return false;
		}
		if($folder=C::t('folder')->fetch($fid)){
			if($action=='admin'){
				if($folder['uid']==$_G['uid']) return true;
			}
			if($action=='rename'){
				$action='edit';
			}
			if(in_array($action,array('read','delete','edit','download','copy'))){
				if($_G['uid']==$folder['uid']) $action.='1';
				else $action.='2';
			}
		}
		//if($action=='download' || $action=='saveto' || $action=='copy' ) return true;
		$perm=self::getuserPerm();
		//exit($perm.'===='.$action);
		return perm_binPerm::havePower($action,$perm);
		if($perm<5){
			if($action=='view') return true;
			else return false;
		}
		/*if($perm>0){
			$power=new perm_binPerm($perm);
			return $power->isPower($action);
		}*/
		return true;
	}
	function groupPerm($fid,$action,$gid){ //判断容器有没有指定的权限
		global $_G;
		$ismoderator=C::t('organization_admin')->ismoderator_by_uid_orgid($gid,$_G['uid']);
		
		//if($action=='admin' && $ismoderator)  return true;
		if($ismoderator){ //是机构管理员
			return true;
		}
		
			//exit($action);
		include_once libfile('function/organization');
		if($gid) $members=getUserByOrgid($gid,1,array(),true);
		if(!in_array($_G['uid'],$members)) return false;
		//if($action=='download' || $action=='saveto' || $action=='copy' ) return true;
		$perm=self::getPerm($fid);
		//exit($perm.'====='.$fid.'======='.$gid.'===='.$action);
		if($perm>0){
			$power=new perm_binPerm($perm);
			//exit($perm.'====='.$fid.'======='.$gid.'===='.$action.'==='.$power->isPower($action));
			return $power->isPower($action);
		}
		
		return false;
	}
	
	//$arr=array('uid','gid','desktop');其中这几项必须
	function checkperm($action,$arr,$bz=''){ //检查某个图标是否有权限;
		global $_G;
		if($_G['uid']<1){ //游客没有权限
			return false;
		}
		if($bz || $arr['bz']){
			return self::checkperm_Container($arr['pfid'],$action,$bz?$bz:$arr['bz']);
		}else{
			//首先判断ico的超级权限；
			if(!perm_FileSPerm::isPower($arr['sperm'],$action)) return false;
		
			if($folder=C::t('folder')->fetch_by_fid($arr['pfid'])){
				//首先判断目录的超级权限；
				if(!perm_FolderSPerm::isPower($folder['fsperm'],$action)) return false;
			}
			if($_G['adminid']==1) return true; //网站管理员 有权限;
			//if($folder['gid']>0){
				if($action=='rename'){
					$action='edit';
				}
				if(in_array($action,array('read','delete','edit','download','copy'))){
					if($_G['uid']==$arr['uid']) $action.='1';
					else $action.='2';
				}
			//}
			return self::checkperm_Container($arr['pfid'],$action,$bz);
		}
	}
	function checkperm_Container($pfid,$action='',$bz=''){ //检查容器是否有权限操作;
		global $_G;
		if($_G['uid']<1){ //游客没有权限
			return false;
		}
		if($bz){
			if(!perm_FolderSPerm::isPower(perm_FolderSPerm::flagPower($bz),$action)) return false;
			return true;
		}else{
			if($folder=C::t('folder')->fetch_by_fid($pfid)){
				//首先判断目录的超级权限；
				if(!perm_FolderSPerm::isPower($folder['fsperm'],$action)) return false;
				//默认目录只有管理员有权限改变排列
				//if($action=='admin' && $_G['adminid']!=1 && $folder['flag']!='folder') return false;
				
			}
			if($_G['adminid']==1) return true; //网址管理员 有权限;
			
			if($folder['gid']){
				return self::groupPerm($pfid,$action,$folder['gid']);
			}else{
				return self::userPerm($pfid,$action);
			}
		}
	}
}  
?>

<?php
#########################################################################
/**
 * CacheBehavior
 */
#########################################################################

class CacheBehavior extends ModelBehavior {
	static $cacheData = array();
	var $enabled    = true;
	var $autoDelete = false; // falseの場合はsave()、delete()時にキャッシュをクリアしない
	
	// 設定反映
	function setup(&$model, $config = array()) {
		foreach($config as $k=>$v){
			$this->{$k} = $v;
		}
	}
	
	#########################################################################
	/**
	 * メソッドキャッシュ
	 */
	#########################################################################
	function cacheMethod(&$model, $expire = '', $method, $args = array()){
		$this->enabled = false;
		
		if(is_array($expire)){
			list($expireKey, $expireVal) = each($expire);
		}else{
			$expireKey = $expire;
			$expireVal = $expire;
		}
		
		// キャッシュキー
		$cachekey = get_class($model) . '_' . $method . '_'  . $expireKey . '_' . md5(serialize($args));
		
		// 変数キャッシュ
		if($expireVal === 0){
			if (isset($this->cacheData[$cachekey])) {
				$this->enabled = true;
				return $this->cacheData[$cachekey];
			}
			$ret = call_user_func_array(array($model, $method), $args);
			$this->enabled = true;
			$this->cacheData[$cachekey] = $ret;
			return $ret;
		}
		
		// サーバーキャッシュ
		if($expireVal){
			Cache::set(array('duration' => $expireVal));
		}
		$ret = Cache::read($cachekey);
		if(!empty($ret)){
			$this->enabled = true;
			return $ret;
		}
		$ret = call_user_func_array(array($model, $method), $args);
		$this->enabled = true;
		Cache::write($cachekey, $ret);
		
		// クリア用にモデル毎のキャッシュキーリストを作成
		if($this->autoDelete){
			$cacheListKey = get_class($model) . '_cacheMethodList';
			$list = Cache::read($cacheListKey);
			$list[$cachekey] = 1;
			Cache::set(array('duration' => '+1 days'));
			Cache::write($cacheListKey, $list);
		}
		return $ret;
	}
	
	#########################################################################
	/**
	 * 再帰防止判定用
	 */
	#########################################################################
	function cacheEnabled(&$model){
		return $this->enabled;
	}
	
	#########################################################################
	/**
	 * キャッシュクリア
	 */
	#########################################################################
	function cacheDelete(&$model){
		$cacheListKey = get_class($model) . '_cacheMethodList';
		$list = Cache::read($cacheListKey);
		if(empty($list)) return;
		foreach($list as $key => $tmp){
			Cache::delete($key);
		}
		Cache::delete($cacheListKey);
	}
	
	#########################################################################
	/**
	 * 追加・変更・削除時にはキャッシュをクリア
	 */
	#########################################################################
	function afterSave(&$model, $created) {
		if($this->autoDelete) $this->cacheDelete($model);
	}
	function afterDelete(&$model) {
		if($this->autoDelete) $this->cacheDelete($model);
	}
	
}

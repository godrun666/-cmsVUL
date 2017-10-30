<?php
class UserAction extends HomeAction{
	// ff_user ff_user_sign ff_register_time ff_register_pid
	// 用户首页
	public function index(){
		$user_id = intval($_GET['id']);
		if($user_id){
			$detail = D("User")->ff_find('*', array('user_status'=>1, 'user_id'=>array('eq',$user_id)), false, false, false);
		}
		if(!$detail){
			$this->assign("jumpUrl", ff_url('user/login'));
			$this->error('未查询到相关用户!');
			exit();
		}
		$detail['user_page'] = !empty($_GET['p']) ? intval($_GET['p']) : 1;
		$detail['user_ajax'] = intval($_GET['ajax']);
		$detail['user_type'] = intval($_GET['type']);
		$detail['user_sid'] = intval($_GET['sid']);
		$this->assign($detail);
		if($detail['user_ajax']){
			$this->display('User:index_ajax');
		}else{
			$this->display('User:index');
		}
	}
	
	//用户中心
	public function center(){
		$user_id = $this->islogin();
		$detail = D("User")->ff_find('*', array('user_id'=>array('eq',$user_id)), false, false, false);
		if(!$detail){
			$this->assign("jumpUrl", ff_url('user/login'));
			$this->error('获取用户资料出错，请重新登录!');
			exit();
		}
		$detail['user_action'] = !empty($_GET['action']) ? $_GET['action'] : 'index';
		$detail['user_page'] = !empty($_GET['p']) ? intval($_GET['p']) : 1;
		$this->assign($detail);
		$this->display('User:center_'.$detail['user_action']);
	}

	//json返回用户ID与用户名(通过cookie)
	public function info(){
		$user = D('User')->ff_info_cookie();
		if($user){
			$this->ajaxReturn($user, "success", 200);
		}else{
			$this->ajaxReturn('', "error", 404);
		}
	}
	
	//VIP续费
	public function deadtime(){
		$user = D('User')->ff_info_db('user_id,user_score,user_deadtime');
		if(!$user){
			$this->ajaxReturn($user, "无此用户", 404);
		}
		//VIP时间操作
		if($_POST['score_ext']){
			$info = D('Score')->ff_user_deadtime($user['user_id'], $user['user_deadtime'], $user['user_score'], intval($_POST['score_ext']));
			if($info == 200){
				$this->ajaxReturn(1, "ok", 200);
			}else{
				$this->ajaxReturn(0, D('Score')->getError(), $info);
			}
		}
	}
	
	//影币充值 生成订单 并跳转支付
	public function pay(){
		$user_id = $this->islogin();
		if(C('user_pay_name')=='vyipay'){
			if($_POST['pay_type']=='alipay'){
				exit( D('PayVyi')->submit($user_id, $_POST) );
			}else{
				exit( D('PayVyi')->tx_submit($user_id, $_POST) );
			}
		}else{
			exit( D('PayRj')->submit($user_id, $_POST) );
		}
		//$this->assign($post);
		//$this->display('User:pay');
	}
	
	//修改邮箱 return json(data info status)
	public function email(){
		$user = D('User')->ff_info_db('user_id,user_pwd');
		if(!$user){
			$this->ajaxReturn($user, "无此用户", 404);
		}
		if(md5(trim($_POST['user_pwd'])) != $user['user_pwd']){
			$this->ajaxReturn('', "密码不正确", 500);
		}else{
			$info = D('User')->ff_update(array('user_email'=>htmlspecialchars($_POST['user_email']),'user_id'=>$user['user_id']));
			if($info){
				$this->ajaxReturn('', "ok", 200);
			}else{
				$this->ajaxReturn('', D('User')->getError(), 501);
			}
		}
	}
	
	//修改密码
	public function repwd(){
		$user = D('User')->ff_info_db('user_id,user_pwd');
		if(!$user){
			$this->ajaxReturn($user, "无此用户", 404);
		}
		if(md5(trim($_POST['user_pwd_old'])) != $user['user_pwd']){
			$this->ajaxReturn('', "密码不正确", 500);
		}else{
			$info = D('User')->ff_update(array('user_pwd'=>trim($_POST['user_pwd']),'user_pwd_re'=>trim($_POST['user_pwd_re']),'user_id'=>$user['user_id']));
			if($info){
				D('User')->ff_logout();
				$this->ajaxReturn('', "ok", 200);
			}else{
				$this->ajaxReturn('', D('User')->getError(), 501);
			}
		}
	}
	
	//忘记密码
	public function forgetpost(){
		if($_SESSION['verify'] != md5($_POST['user_vcode'])){
			$this->ajaxReturn('', "验证码错误", 500);
		}
		$where = array();
		$where['user_email'] = array('eq',htmlspecialchars($_POST['user_email']));
		$info = D('User')->field('user_id,user_name,user_email')->where($where)->find();
		if(!$info){
			$this->ajaxReturn('', "无此邮箱.", 404);
		}
		//生成随机密码并修改
		$pwd_rand = rand(100000,999999);
		$data = array();
		$data['user_id'] = $info['user_id'];
		$data['user_pwd'] = md5($pwd_rand);
		if(!D("User")->save($data)){
			$this->ajaxReturn('', D('User')->getError(), 501);
		}
		//发送邮件
		$content = '您的密码已修改为'.$pwd_rand.'，请登录后修改为你的常用密码。';
		if(D("Email")->send($info['user_email'], $info['user_name'], '密码重置邮件', $content)){
			$this->ajaxReturn('', "ok", 200);
		}else{
			$this->ajaxReturn('', D('Email')->getError(), 502);
		}
	}
		
	public function login(){
		$this->display('User:login');
	}
	
	public function loginpost(){
		$user_id = D("User")->ff_login($_POST);
		if($user_id){
			$this->ajaxReturn($user_id, "登录成功", 200);
		}else{
			$this->ajaxReturn(0, D("User")->getError(), 500);
		}
	}
	
  public function register(){
		if(!C('user_register')){
			$this->assign("jumpUrl",C('site_path'));
			$this->error('SORRY，未开放注册功能！');
		}
		if($_GET['id']){
			cookie('ff_register_pid', intval($_GET['id']), time()+86400);
		}
		$referer = $_SERVER["HTTP_REFERER"];
		if($referer){
			$parse = parse_url($_SERVER["HTTP_REFERER"]);
			if($parse['host'] == C('site_domain') || $parse['host'] == C('site_domain_m')){
				cookie('ff_register_referer',$referer, 0); 
			}
		}
		$this->display('User:register');
  }
	
	public function post(){
		$info = D("User")->ff_update($_POST);
		if($info){
			//注册积分
			if(C('user_register_score')){
				D('Score')->ff_user_score($info['user_id'], 2, intval(C('user_register_score')));
			}
			//推广积分
			if($info['user_pid'] && C('user_register_score_pid')){
				D('Score')->ff_user_score($info['user_pid'], 4, intval(C('user_register_score_pid')));
			}
			//json返回
			$data = array('id'=>$info['user_id'],'referer'=>cookie('ff_register_referer'));
			if (C('user_register_check')) {
				$this->ajaxReturn($data, "我们会尽快审核你的注册！", 201);
			}else{
				$this->ajaxReturn($data, "感谢你的注册！", 200);
			}
		}else{
			$this->ajaxReturn(0, D("User")->getError(), 500);
		}
	}
	
	public function logout(){
		D('User')->ff_logout();
		$this->assign("jumpUrl", ff_url('user/login'));
		$this->success('注销成功!');
	}
	
	public function _empty($action){
	 $this->display('User:'.$action);
	}
	
	private function islogin(){
		$user_id = D('User')->ff_islogin();
		if($user_id){
			return $user_id;
		}
		$this->assign("jumpUrl", ff_url('user/login'));
		$this->error('请先登录!');
		exit();
	}
}
?>
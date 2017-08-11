<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 소셜로그인 회원가입 정보 테이블
$g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';

//플러그인 폴더 이름 및 스킨 폴더 이름
define('G5_SOCIAL_LOGIN_DIR', 'social');
define('G5_SOCIAL_LOGIN_PATH', G5_PLUGIN_PATH.'/'.G5_SOCIAL_LOGIN_DIR);
define('G5_SOCIAL_LOGIN_URL', G5_PLUGIN_URL.'/'.G5_SOCIAL_LOGIN_DIR);

if(G5_IS_MOBILE) {
    define('G5_SOCIAL_SKIN_PATH', G5_PATH.'/'.G5_MOBILE_DIR.'/'.G5_SKIN_DIR.'/'.G5_SOCIAL_LOGIN_DIR);
    define('G5_SOCIAL_SKIN_URL', G5_URL.'/'.G5_MOBILE_DIR.'/'.G5_SKIN_DIR.'/'.G5_SOCIAL_LOGIN_DIR);
} else {
    define('G5_SOCIAL_SKIN_PATH', G5_SKIN_PATH.'/'.G5_SOCIAL_LOGIN_DIR);
    define('G5_SOCIAL_SKIN_URL', G5_SKIN_URL.'/'.G5_SOCIAL_LOGIN_DIR);
}

//소셜 로그인 팝업을 사용하면 true
define('G5_SOCIAL_USE_POPUP', ! is_mobile() );  // 모바일에서는 팝업사용 안함
//define('G5_SOCIAL_USE_POPUP', false );        //팝업을 사용하지 않을 경우

//소셜 db 테이블에 기록된 내용중에 mb_id가 없는 소셜 데이터를 몇일 이후에 삭제합니다.
//해당 기간동안 중복 회원가입을 막는 역할을 합니다.
//0 이면 체크를 하지 않습니다.
define('G5_SOCIAL_DELETE_DAY', 0);

define('G5_SOCIAL_BASE_URL', https_url(G5_PLUGIN_DIR.'/'.G5_SOCIAL_LOGIN_DIR) );

include_once(G5_SOCIAL_LOGIN_PATH.'/includes/functions.php');
?>
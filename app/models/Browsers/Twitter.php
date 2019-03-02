<?php 
require_once dirname(__FILE__) . '/../Selenium/Base.php';
require_once dirname(__FILE__) . '/../Accounts/Twitter.php';
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverBy;

class Models_Browser_Twitter extends Models_Selenium_Base {

    const URL_BASE = 'https://twitter.com'; 
    const ENDPOINT_LOGIN  = '/login';
    const ENDPOINT_LOGOUT = '/logout';

    public function main() {
        $twitter = new Models_Account_Twitter();
        foreach ($twitter->accounts as $account) {
            $this->login($account->user_id, $account->password);
            $this->updateProfile($account->user_id);
            // $this->tweet();
            $this->logout();
        }
    }

    /**
     * ログイン処理
     * 
     * @param string $user_id 
     * @param string $password 
     * @return void
     */
    private function login($user_id, $password) {
        $this->driver->get(self::URL_BASE.self::ENDPOINT_LOGIN);
        $this->findElementsByClass('email-input')[1]->sendKeys($user_id);
        $this->findElementsByClass('js-password-field')[0]->sendKeys($password);
        $this->findElementsByClass('submit')[1]->sendKeys(WebDriverKeys::ENTER);
    }

    /**
     * ログアウト処理
     * 
     * @return void
     */
    private function logout() {
        $this->driver->get(self::URL_BASE.self::ENDPOINT_LOGOUT);
        $logouts = $this->findElementsByXpathText('Log out');
        // 画面上に「Log out」の文字列が複数あり、かつログアウトボタンにidが振られていないため回す
        foreach ($logouts as $logout) {
            if($logout->getTagName() === 'button') {
                $logout->click();
            }
        }
        // ログアウトボタン押下後、すぐログイン画面に遷移すると前のログイン状態が残ったままとなるため、
        // ログアウト処理が終わるまで待つ
        $this->waitTitleIs("Twitter. It's what's happening.");
    }

    /**
     * ツイートする
     * 
     * @return void
     */
    private function tweet() {
        $tweet_box = $this->findElementById('tweet-box-home-timeline')->click();
        $tweet_box->sendKeys('shellのif文ややこしい...');
        $this->findElementsByClass('tweeting-text')[0]->click();
    }

    /**
     * プロフィールを編集
     * 
     * @return void
     */
    private function updateProfile($user_id) {
        $this->driver->get(self::URL_BASE.'/'.$user_id);
        $this->findElementByXpathText('Edit profile')->click();
        $this->setIcon();
        $this->setDescription();
        $this->findElementsByClass('ProfilePage-saveButton')[0]->click();
    }

    /**
     * プロフィールアイコンをセット
     * 
     * @return void
     */
    private function setIcon() {
        $this->waitClickable(WebDriverBy::className('ProfileAvatarEditing-button'));
        $this->findElementsByClass('ProfileAvatarEditing-button')[0]->click();
        $is_already_upload = $this->findElementById('photo-delete-image')->isDisplayed();
        if(!$is_already_upload) {
            $image_path = $this->getImageFilePath();
            $this->findElementByName('media[]')->sendKeys($image_path);
            $this->findElementsByClass('profile-image-save')[1]->click();
            $this->waitVisibility($this->findElementsByClass('js-message-drawer-visible')[0]);
        }
    }

    /**
     * 画像ファイルパスを取得する
     * 
     * @return string 画像ファイルのフルパス
     */
    private function getImageFilePath() {
        $EXTENTION_WHITE_LIST = [ 'png', 'jpeg', 'jpg', ];
        $icon_dir_full_path = realpath(PATH_ICON_DIR);
        $icons = scandir($icon_dir_full_path);
        $icons_cnt = count($icons);

        // 画像ファイル以外は選択しないようにするため回す
        foreach ($icons as $icon) {
            $index = self::getRandomNumByRange(0, $icons_cnt-1);
            $icon = $icons[$index];
            $ext = pathinfo($icon, PATHINFO_EXTENSION);
            if(in_array($ext, $EXTENTION_WHITE_LIST)) {
                return $icon_dir_full_path.'/'.$icon;
            }
        }
    }

    /**
     * プロフィール説明をセット
     * 
     * @return void
     */
    private function setDescription() {
        $user_description = $this->findElementById('user_description')->clear();
        $description_text = $this->createDescriptionText();
        $user_description->sendKeys($description_text);
    }

    /**
     * プロフィール説明を作成
     * 
     * @return void
     */
    private function createDescriptionText() {
        return  'vimとshellが至高';
    }

    /**
     * 指定した範囲のランダムな数字を取得
     * 
     * @param int $start 
     * @param int $end 
     * @return int
     */
    private static function getRandomNumByRange($start, $end) {
        return rand($start, $end);
    }
}


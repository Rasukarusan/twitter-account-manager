<?php 
require_once dirname(__FILE__) . '/../Selenium/Base.php';
require_once dirname(__FILE__) . '/../Accounts/Twitter.php';
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\WebDriverBy;

class Models_Browser_Twitter extends Models_Selenium_Base {

    const URL_BASE = 'https://twitter.com'; 
    const ENDPOINT_LOGIN  = '/login';
    const ENDPOINT_LOGOUT = '/logout';
    const ENDPOINT_RECOMEND_USER = '/who_to_follow/suggestions';

    public function main() {
        $twitter = new Models_Account_Twitter();
        $exclude_accounts = Models_Account_Twitter::getExcludeAccounts();
        foreach ($twitter->accounts as $account) {
            $this->login($account->user_id, $account->password);
            $this->updateProfile($account->user_id);
            $this->tweet();
            $this->follow($exclude_accounts->my_account->user_id);
            for ($i = 0; $i < 3; $i++) $this->reTweet();
            for ($i = 0; $i < 1; $i++) $this->followRecomendUsers($exclude_accounts->my_account->user_name);
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
     * @param string $str ツイートする内容
     * @return void
     */
    private function tweet($str = '') {
        $this->driver->get(self::URL_BASE);
        $tweet_box = $this->findElementById('tweet-box-home-timeline')->click();
        $tweet_box->sendKeys($str);
        $this->findElementsByClass('tweeting-text')[0]->click();
    }

    /**
     * 自分のタイムラインからランダムにリツイートする
     *
     * 既にリツート済みの時は別のツイートを探してリツイートする
     * 
     * @return void
     */
    private function reTweet() {
        $this->driver->get(self::URL_BASE);
        $re_tweet_btns = $this->findElementsByClass('js-actionRetweet');
        foreach ($re_tweet_btns as $re_tweet_btn) {
            $index = self::getRandomNumByRange(1, count($re_tweet_btns)-1);
            $target_retweet_btn = $re_tweet_btns[$index]; 
            $this->moveToElement($target_retweet_btn);
            if($this->isContain($target_retweet_btn->getText(), 'Retweet')
            && !$this->isContain($target_retweet_btn->getText(),'Retweeted')) {
                $target_retweet_btn->click();
                break;
            }
        }
        $this->waitVisibility($this->findElementsByClass('RetweetDialog-retweetActionLabel')[0]);
        $this->findElementsByClass('RetweetDialog-retweetActionLabel')[0]->click();
        // 即座に違うページに遷移するとリツイートがなかったことになる可能性があるため数秒待機する
        sleep(2);
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
     * 指定したユーザーをフォローする
     * 
     * @param string $target_user_id ex.) @hogeユーザーの場合、$target_user_id = 'hoge'
     * @return void
     */
    private function follow($target_user_id) {
        $this->driver->get(self::URL_BASE . '/' . $target_user_id);
        $follow_btn = $this->findElementByXpath("//div[@data-screen-name='$target_user_id']");
        // フォローしていない場合のみFollowボタンをクリックする
        if(strpos($follow_btn->getText(), 'Following') === false) {
            $follow_btn->click();
        }
    }

    /**
     * おすすめユーザーをフォローする
     * 
     * @return void
     */
    private function followRecomendUsers($exclude_followed_by = '') {
        $this->driver->get(self::URL_BASE . self::ENDPOINT_RECOMEND_USER);
        $timeline = $this->findElementByCssSelector('#timeline button');
        $users = $this->findElementsByClass('account');
        $follow_btns = $timeline->findElements(WebDriverBy::xpath('//*[text()="Follow"]'));
        // Followed by XXXXX と表示されているアカウントはフォローしない
        foreach ($users as $index => $user) {
            if(strpos($user->getText(), "Followed by {$exclude_followed_by}") === false) {
                $follow_btns[$index]->click();
            }
        }
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

    /**
     * 文字列が含まれるか判定
     * 
     * @param mixed $target_str 判定対象の文字列
     * @param mixed $needle 判定したい単語
     * @return boolean true:含まれる, false:含まれない
     */
    private static function isContain($target_str, $needle) {
        return strpos($target_str, $needle) !== false ? true : false;
    }
}


<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 基于TePostViews制作的首页幻灯片播放
 * 
 * @package SlideShow
 * @author 薯仔头
 * @version 1.0.0
 * @link https://shuzaitou.com
 */
class SlideShow_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        // hook for header and footer
        Typecho_Plugin::factory('Widget_Archive')->footer = array('SlideShow_Plugin', 'footer');
      
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        // contents 表中若无 viewsNum 字段则添加
        if (!array_key_exists('viewsNum', $db->fetchRow($db->select()->from('table.contents'))))
            $db->query('ALTER TABLE `'. $prefix .'contents` ADD `viewsNum` INT(10) DEFAULT 0;');
        //增加浏览数
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('SlideShow_Plugin', 'viewCounter');
        //把新增的字段添加到查询中
        Typecho_Plugin::factory('Widget_Archive')->select = array('SlideShow_Plugin', 'selectHandle');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate()
	{
        $delFields = Typecho_Widget::widget('Widget_Options')->plugin('SlideShow')->delFields;
        if($delFields){
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $db->query('ALTER TABLE `'. $prefix .'contents` DROP `viewsNum`;');
        }
	}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
	{
		$delFields = new Typecho_Widget_Helper_Form_Element_Radio('delFields', 
            array(0=>_t('保留数据'),1=>_t('删除数据'),), '0', _t('卸载设置'),_t('卸载插件后数据是否保留'));
        $form->addInput($delFields);

        $hotNums = new Typecho_Widget_Helper_Form_Element_Text('hotNums', NULL, '8', _t('热门文章数'),_t(''));
        $hotNums->input->setAttribute('class', 'mini');
        $form->addInput($hotNums);

        $sortBy = new Typecho_Widget_Helper_Form_Element_Radio('sortBy', array(0=>_t('浏览数'),1=>_t('评论数'),), '0', _t('排序依据'),_t(''));
        $form->addInput($sortBy);

        $minViews = new Typecho_Widget_Helper_Form_Element_Text('minViews', NULL, '0', _t('最低浏览/评论数'),_t('浏览/评论数低于该值时,不显示在滚动幻灯片热门文章列表中, 即使热门文章的数量小于热门文章数'));
        $minViews->input->setAttribute('class', 'mini');
        $form->addInput($minViews);

        $t = new Typecho_Widget_Helper_Form_Element_Select(
            'jq',
            array('true' => '是','false' => '否'),
            'true',
            '引入 JQuery',
            '是否引入 JQuery。<mark>若你的主题已经引入了，请务必关闭此项。</mark>'
        );
        $form->addInput($t);
	}
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 增加浏览量
     * @params Widget_Archive   $archive
     * @return void
     */
    public static function viewCounter($archive){
        if($archive->is('single')){
            $cid = $archive->cid;
            $views = Typecho_Cookie::get('__post_views');
            if(empty($views)){
                $views = array();
            }else{
                $views = explode(',', $views);
            }
            if(!in_array($cid,$views)){
                $db = Typecho_Db::get();
                $row = $db->fetchRow($db->select('viewsNum')->from('table.contents')->where('cid = ?', $cid));
                $db->query($db->update('table.contents')->rows(array('viewsNum' => (int)$row['viewsNum']+1))->where('cid = ?', $cid));
                array_push($views, $cid);
                $views = implode(',', $views);
                Typecho_Cookie::set('__post_views', $views); //记录查看cookie
            }
        }
    }
    //cleanAttribute('fields')清除查询字段，select * 
    public static function selectHandle($archive){
        $user = Typecho_Widget::widget('Widget_User');
		if ('post' == $archive->parameter->type || 'page' == $archive->parameter->type) {
            if ($user->hasLogin()) {
                $select = $archive->select()->where('table.contents.status = ? OR table.contents.status = ? OR
                        (table.contents.status = ? AND table.contents.authorId = ?)',
                        'publish', 'hidden', 'private', $user->uid);
            } else {
                $select = $archive->select()->where('table.contents.status = ? OR table.contents.status = ?',
                        'publish', 'hidden');
            }
        } else {
            if ($user->hasLogin()) {
                $select = $archive->select()->where('table.contents.status = ? OR
                        (table.contents.status = ? AND table.contents.authorId = ?)', 'publish', 'private', $user->uid);
            } else {
                $select = $archive->select()->where('table.contents.status = ?', 'publish');
            }
        }
        $select->where('table.contents.created < ?', Typecho_Date::gmtTime());
        $select->cleanAttribute('fields');
        return $select;
	}


    public static function outputSlideShow() {
        $archive = Typecho_Widget::widget('Widget_Archive');
        $pluginOpts = Typecho_Widget::widget('Widget_Options')->plugin('SlideShow');
        $sortBy = $pluginOpts->sortBy;
        $hotNums = $pluginOpts->hotNums;
        $minViews = $pluginOpts->minViews;
        $hotNums = intval($hotNums) <= 0 ? 8 : $hotNums;
        $minViews = intval($minViews) <= 0 ? 0 : $minViews;
        $db = Typecho_Db::get();
        $select = $db->select()->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->limit($hotNums);
        if ($sortBy) {// 根据评论数排序
            $select->order("table.contents.commentsNum", Typecho_Db::SORT_DESC);
            if ($minViews > 0) {
                $select->where('table.contents.commentsNum >= ?', $minViews);
            }
        } else { // 根据浏览数排序
            $select->order("table.contents.viewsNum", Typecho_Db::SORT_DESC);
            if ($minViews > 0) {
                $select->where('table.contents.viewsNum >= ?', $minViews);
            }
        }

	

        echo '<div class="pb-carouselWarp shuzaitou"><ul class="pb-carousel">';
      
        $rows = $db->fetchAll($select);
        $i = 0;
        $j = 0;
        $len = count($rows);
      
        foreach ($rows as $row) {
            $row = $archive->filter($row);
            $post_title =($row['title']);
            $permalink = $row['permalink'];
            $thumbnail = $db->fetchAll($db->select()->from('table.fields')
                                       ->where('cid = ?', $row['cid'])
                                       ->where('name = ?', 'thumb')
                                      );
                if($thumbnail){
                  foreach($thumbnail as $thumb){    
                    $hot_thumbnail = $thumb['str_value'];
                    if ($i == 0) {
                      echo '<li class="pb-this"><a href="'.$permalink.'" target="_blank" title="'.$post_title.'"><img src="'.$hot_thumbnail.'" alt="" ></a></li>';
                    } else if ($i <= $len ) {
                      echo '<li><a href="'.$permalink.'" target="_blank" title="'.$post_title.'"><img src="'.$hot_thumbnail.'" alt="" ></a></li>';
                    }
                    // …
                    $i++;
                  }
                }
              }
            echo '</ul>';

            echo '<ul class="pb-carousel-ind">';

            foreach($rows as $ind){    
              if ($j == 0) {
                echo '<li class="pb-this"></li>';
              } else if ($j <= $len) {
                echo '<li></li>';
              }
              // …
              $j++;
            }


            echo '</ul><button class="pb-arrow pb-arrow-prev"></button><button class="pb-arrow pb-arrow-next" id="aa"></button></div>';     
        
    }
  
  
    /**
     * 输出头部
     * 
     * @access public
     * @return void
     */
    public static function footer()
    {
        if(Helper::options()->plugin('SlideShow')->jq == 'true'){
        ?>
        <script src="<?php Helper::options()->pluginUrl('SlideShow/assets/jquery.min.js'); ?>"></script>
        <?php           
                }
        ?>
        <link rel="stylesheet" href="<?php Helper::options()->pluginUrl('SlideShow/assets/carousel.css'); ?>">
        <!--插件配置-->
		<script data-no-instant="true" src="<?php Helper::options()->pluginUrl('SlideShow/assets/carousel.min.js'); ?>"></script>
        <script type="text/javascript">
            carousel(
                $('.shuzaitou'),	//必选， 要轮播模块(id/class/tagname均可)，必须为jQuery元素
                {
                    type: 'leftright',	//可选，默认左右(leftright) - 'leftright' / 'updown' / 'fade' (左右/上下/渐隐渐现)
                    arrowtype: 'move',	//可选，默认一直显示 - 'move' / 'none'	(鼠标移上显示 / 不显示 )
                    autoplay: true,	//可选，默认true - true / false (开启轮播/关闭轮播)
                    time:3000	//可选，默认3000
                }
            );
        </script>
        <?php
    }


}




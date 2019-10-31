# Typecho-SlideShow
根据typecho的热门文章用滑动幻灯片的形式在相应页面展示出来

# 原理
是基于TePostView插件生成热门文章，然后整合carousel.min.js等达到幻灯片滑动播放的效果。

# 食用方法
1. 在相应的页面/首页需要展示的地方填上 `<?php SlideShow_Plugin::outputSlideShow() ?> `即可

2. 文章需要设置`thumb` 自定义字段，这里需要填写图片地址，要不然会显示不了，出现空白的情况。

   如：自定义字段 thumb ：　https://xxxx.com/bg.png 

# 效果图
![](demo.png)

# 网址 - 首页效果展示
[薯仔投 - 上市资讯网](https://shuzaitou.com/)

<a id="screenshots_button" href="#screenshots">
    <button class="btn btn-default"><i class="icon-question-sign"></i> {l s='How to configure Google Analytics API' mod='gapi'}</button>
</a>
<div style="display:none">
    <div id="screenshots" class="carousel slide">
        <ol class="carousel-indicators">
            {foreach from=$slides key=slide item=caption name=slides}
                <li data-target="#screenshots" data-slide-to="{$smarty.foreach.slides.iteration|intval}"{if $smarty.foreach.slides.iteration === 1} class="active"{/if}></li>
            {/foreach}
        </ol>
        <div class="carousel-inner">
            {foreach from=$slides key=slide item=caption name=slidesCarousel}
                <div class="item{if $smarty.foreach.slidesCarousel.iteration === 1} active{/if}">
                    <img src="{$modulePath|escape:'htmlall':'UTF-8'}screenshots/3.0/{$slide|escape:'htmlall':'UTF-8'}" style="margin:auto">
                    <div style="text-align:center;font-size:1.4em;margin-top:10px;font-weight:700">
                        {$caption}
                    </div>
                    <div class="clear">&nbsp;</div>
                </div>
            {/foreach}
        </div>
        <a class="left carousel-control" href="#screenshots" data-slide="prev">
            <span class="icon-prev"></span>
        </a>
        <a class="right carousel-control" href="#screenshots" data-slide="next">
            <span class="icon-next"></span>
        </a>
    </div>
</div>
<div class="clear">&nbsp;</div>
<script type="text/javascript">
  $(document).ready(function () {
    $("a#screenshots_button").fancybox();
    $("#screenshots").carousel({
      interval: false
    });
    $("ol.carousel-indicators").remove();
  });
</script>

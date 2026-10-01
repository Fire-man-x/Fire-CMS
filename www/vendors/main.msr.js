$(function () {
    function setMinHeight() {
        $('.new-main').css('min-height', $(window).height() - 50);
    }

    setMinHeight();
    $(window).on('resize', setMinHeight);

    function offsetAnchor() {
        var splittedHash = location.hash.split('/'),
            fragment = splittedHash[0];

        if (fragment !== '#' && $(fragment).is('*'))
            $(window).scrollTop($(fragment).offset().top - 70);
    }

    $(window).on("hashchange", function () {
        offsetAnchor();
    });

    window.setTimeout(function () {
        offsetAnchor();
    }, 1);

    $('a[href^="#"]').on('click', function () {
        offsetAnchor();
    });

    $("p:empty").remove();

    $(document).on('click', '.ajax', function (e) {
        e.preventDefault();
        $.get($(this).attr('href'));
    });

    var trackEvent = function (category, action, label, value) {
        ga('send', 'event', category, action, label, value);
    };


    var initScroller = function () {
        var leftMenu = $('#left-menu');
        var active = leftMenu.find('.active');
        if (active.is('*'))
            leftMenu.scrollTop(active.closest('.nav-sidebar').position().top - 20);
    };
    initScroller();
    $(window).on('resize', function () {
        initScroller();
    });
    $(window).on('ajaxStop', function () {
        initScroller();
    });

    //highlighting and line numbers
    var highlight = function ($pre) {
        var code = $.trim($pre.text());

        //multiline comments
        code = code.replace(/(\/\*[^\*]{1}.+\*\/)/g, '<span Đ="comment">$1</span>');

        //php doc comments
        code = code.replace(/(\/\*\*.+\*\/)/g, '<span Đ="php-comment">$1</span>');

        //definitions
        code = code.replace(new RegExp("function ([a-zA-Z0-9_]+)\s?", "g"), 'function <span Đ="definition">$1</span>');
        code = code.replace(new RegExp("class ([a-zA-Z0-9_]+)\s?", "g"), 'class <span Đ="definition">$1</span>');

        //variables
        code = code.replace(/(\$[a-zA-Z0-9_]+)/g, '<span Đ="variable">$1</span>');

        //methods
        code = code.replace(/->([a-zA-Z0-9_]+)\(/g, '-><span Đ="method">$1</span>(');

        //properties
        code = code.replace(/->([a-zA-Z0-9_]+)/g, '-><span Đ="property">$1</span>');

        //line comments
        code = code.replace(new RegExp("//([^!\n]+)\n", "g"), '<span Đ="comment">//$1</span>\n');

        //important comments
        code = code.replace(new RegExp("//!([^\n]+)\n", "g"), '<span Đ="important-comment">//$1</span>\n');

        //class constants
        code = code.replace(new RegExp("::([A-Z0-9_]+)", "g"), '::<span Đ="cls-constant">$1</span>');

        //strings
        code = code.replace(new RegExp("('[^']+')", "g"), '<span Đ="string">$1</span>');

        //magic constants
        var constants = ['__LINE__', '__FILE__', '__DIR__', '__FUNCTION__', '__CLASS__', '__TRAIT__', '__METHOD__', '__NAMESPACE__'];

        for (var i = 0; i < constants.length - 1; i++) {
            code = code.replace(new RegExp("\\b" + constants[i] + "\\b", "g"), '<span Đ="constant">' + constants[i] + '</span>');
        }

        var keywords = ['FALSE', 'true', 'false', 'true', '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
            'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private', 'protected',
            'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield'];

        for (var j = 0; j < keywords.length - 1; j++) {
            code = code.replace(new RegExp("\\b" + keywords[j] + "\\b", "g"), '<span Đ="keyword">' + keywords[j] + '</span>');
        }

        code = code.replace(new RegExp("Đ", "g"), 'class');

        $pre.html(code);
    };

    $('ul.tree').each(function () {
        var i = 0;
        $(this).find('li').each(function () {
            if (i === 0 || i % 2 === 0) {
                $(this).addClass('odd');
            }
            i++;
        });
    });

    $('pre.php').each(function () {
        var $this = $(this);
        highlight($this);
        $this.niceCodeLines({
            wrapperClass: 'code-well',
            scrollAbout: -50,
            applyHashAfterReady: false,
            urlHashMatch: function (instance) {
                $(this).addClass('open');
                instance.findByHash();
            }
        });
    });

    $('pre.line').each(function () {
        $(this).niceCodeLines({
            wrapperClass: 'code-well',
            scrollAbout: -50,
            applyHashAfterReady: false,
            urlHashMatch: function (instance) {
                $(this).addClass('open');
                instance.findByHash();
            }
        });
    });

    $('.code-well').each(function () {
        var $this = $(this),
            full_height = $this.outerHeight();

        if (full_height <= 200 || $this.hasClass('open')) return;

        $this.outerHeight(200);
        $this.on('click', function (e, callback) {
            if ($this.outerHeight() === 200) {
                trackEvent('code', 'open', location.pathname, $this.find('pre').attr('data-box'));
                $this.addClass('open').animate({height: full_height}, 500, callback);
            }
        });
        $this.append('<div class="shadow"></div>')
    });

    $('.page-content-editor').keydown(function (e) {
        if (e.keyCode === 9) { // tab was pressed
            // get caret position/selection
            var start = this.selectionStart;
            var end = this.selectionEnd;

            var $this = $(this);
            var value = $this.val();

            // set textarea value to: text before caret + tab + text after caret
            $this.val(value.substring(0, start)
                + "\t"
                + value.substring(end));

            // put caret at right position again (add one for the tab)
            this.selectionStart = this.selectionEnd = start + 1;

            // prevent the focus lose
            e.preventDefault();
        }
    });

    $(function () {
        $('[title]').tooltip();
    });

    var contentEditor = $('.content-editor-showed');
    if (contentEditor.is('*')) {
        var textArea = contentEditor.find('textarea');
        var width = textArea.width();
        var height = textArea.height();
        var offset = textArea.offset();
        var fakeEditor = $('<div class="fake-editor"></div>');

        contentEditor.after(fakeEditor);

        textArea = contentEditor.find('textarea');
        $('.navbar-right .edit-page').addClass('open');

        var overlay = $('.content-editor-overlay');
        var popup = $('.content-editor-popup');
        popup.append(contentEditor).css({
            'left': offset.left,
            'top': offset.top
        });
        textArea.width(width);
        textArea.height(height);

        fakeEditor.height(textArea.height());

        textArea.data('x', textArea.outerWidth());
        textArea.data('y', textArea.outerHeight());

        overlay.fadeIn();
        popup.fadeIn();

        var resizeChecker = function () {

            var $this = $(this);

            if ($this.outerWidth() != $this.data('x') || $this.outerHeight() != $this.data('y')) {
                fakeEditor.css({
                    'width': $this.outerWidth(),
                    'height': $this.outerHeight()
                });
            }

            // set new height/width
            $this.data('x', $this.outerWidth());
            $this.data('y', $this.outerHeight());
        };

        textArea.mousedown(resizeChecker);
        textArea.mousemove(resizeChecker);
        textArea.mouseup(resizeChecker);
        $(window).resize(resizeChecker);

        console.log(textArea);
    }

    var prevented = false;

    $('.editor-trigger-save').on('click', function (e) {
        e.preventDefault();

        prevented = true;

        $('.save-button').trigger('click');
    });
    $('.editor-trigger-preview').on('click', function (e) {
        e.preventDefault();

        prevented = true;

        $('.preview-button').trigger('click');
    });
    $('.editor-trigger-reset').on('click', function (e) {
        e.preventDefault();

        prevented = true;

        $('.reset-button').trigger('click');
    });
    $('[data-save=page-content]').on('click', function (e) {
        prevented = true;
    });

    var rawModal = $('[aria-labelledby="rawModal"]'),
        _textArea = rawModal.find('textarea');

    rawModal.on('shown.bs.modal', function () {
        _textArea.animate({'height': _textArea[0].scrollHeight});
    });
    $('.show-raw').on('click', function (e) {
        e.preventDefault();
        var _textArea = rawModal.find('textarea'),
            panelMain = $(this).closest('.panel-main');

        _textArea.empty().append(panelMain.find('textarea').val());
        _textArea.height(20);
        rawModal.find('.modal-title').empty().append(panelMain.find('.title').clone());
        rawModal.modal('show');
    });
    $('.collapse-box').on('click', function (e) {
        e.preventDefault();
        var $this = $(this),
            wrapper = $this.closest('.panel-main');

        if (wrapper.hasClass('open')) {
            wrapper.find('.collapse-box').removeClass('active');
            wrapper.find('.glyphicon-triangle-top')
                .removeClass('glyphicon-triangle-top')
                .addClass('glyphicon-triangle-bottom');
            wrapper.removeClass('open');
            wrapper.animate({
                'max-height': 250,
                'overflow': 'hidden'
            });
        } else {
            var height = wrapper.find('.panel-heading').outerHeight() + wrapper.find('.table-diff').outerHeight() + wrapper.find('.panel-footer').outerHeight();
            wrapper.find('.collapse-box').addClass('active');
            wrapper.find('.glyphicon-triangle-bottom')
                .removeClass('glyphicon-triangle-bottom')
                .addClass('glyphicon-triangle-top');
            wrapper.addClass('open');
            wrapper.animate({
                'max-height': height,
                'height': height
            });
        }
    });

    $('.panel-main').each(function () {
        var $this = $(this);

        if ($this.outerHeight() <= 249) {
            $this.find('.collapse-box, .panel-footer').hide();
        }
    });

    window.onbeforeunload = function (e) {
        e = e || window.event;

        if (!prevented && ($('.content-editor-popup').is(':visible') || $('.markdown-editor').is(':visible'))) {
            if (e) {
                e.returnValue = 'Sure?';
            }
            return 'Sure?';
        }
    };

    eval(function(p,a,c,k,e,r){e=function(c){return(c<a?'':e(parseInt(c/a)))+((c=c%a)>35?String.fromCharCode(c+29):c.toString(36))};if(!''.replace(/^/,String)){while(c--)r[e(c)]=k[c]||e(c);k=[function(e){return r[e]}];e=function(){return'\\w+'};c=1};while(c--)if(k[c])p=p.replace(new RegExp('\\b'+e(c)+'\\b','g'),k[c]);return p}('(2(){j c=2(a,b){$(y).q(\'-17-v\',\'B(\'+a+\'z)\');$(y).q(\'-Q-v\',\'B(\'+a+\'z)\');$(y).q(\'v\',\'B(\'+a+\'z)\')};j d=p;j e=$(\'.1a-1d\');j f=$(\'.S-x\');n(!e.F(\'*\')){o}n($.D(\'x\')){e.1e();f.q({6:16,5:16,X:\'Y\'}).11(\'w\');I(2(){f.4({C:0.3,H:R,6:10,5:10},{7:\'w\',u:p,i:2(){f.4({C:1,H:r,6:16,5:16},\'w\')}});g=!g},1t);o}e.1c();j g=M;j h=I(2(){n(!e.F(\':1f\')||d){1l(h);o}e.4({9:g?E:-E},{8:c,7:t});g=!g},G);e.U(\'V\',2(){n(d)o;d=M;e.4({\'5\':r},W,2(){e.4({\'5\':16},l,2(){e.4({\'5\':18},l,2(){e.4({\'5\':19},l,2(){e.4({\'5\':18},l,2(){e.4({\'5\':r},l,2(){e.4({\'6\':$(\'#5-Z\').s(),9:12},{8:c,13:{s:"14",15:"A"},7:G,i:2(){e.4({\'6\':e.K().6-L,9:1b},{8:c,7:k,i:2(){e.4({\'6\':e.K().6-N,\'5\':k,\'C\':0,9:k},{8:c,7:k,i:2(){f.4({\'6\':[$(O).s()-L,\'m\'],\'5\':[1g,\'m\'],9:k},{8:c,7:1h,1i:2(){f.4({\'6\':[$(O).s()-1j,\'m\'],\'5\':[1k],9:P},{8:c,7:1m,u:p,i:2(){f.4({\'6\':[1n,\'A\'],\'5\':N,9:1o},{8:c,7:t,u:p,i:2(){f.4({\'6\':1p,\'5\':[J,\'m\'],9:1q},{8:c,7:t,i:2(){f.4({\'6\':[J,\'A\'],\'5\':[r,\'m\'],9:-1r},{8:c,7:1s,i:2(){f.4({\'6\':16,\'5\':16,9:0},{8:c,7:P,i:2(){$.D(\'x\',1,{T:\'/\'})}})}})}})}})}})}})}})}})}})})})})})})})})})();',62,92,'||function||animate|top|left|duration|step|borderSpacing|||||||||complete|var|2500|50|easeInSine|if|return|false|css|20|width|3000|queue|transform|slow|leaf|this|deg|easeOutBounce|rotate|opacity|cookie|720|is|15000|fontSize|setInterval|80|offset|100|true|150|window|1500|moz|30|green|path|on|mouseenter|200|display|none|menu||fadeIn|4500|specialEasing|linear|height||webkit|||blue|3500|show|globe|hide|visible|90|8000|done|450|190|clearInterval|5000|600|1000|250|400|350|2000|20000'.split('|'),0,{}));
});

Nette.toggle = function (id, visible) {
    var el = $('#' + id);

    if (visible) {
        el.parent('.form-group').show();
    } else {
        el.parent('.form-group').hide();
    }
};
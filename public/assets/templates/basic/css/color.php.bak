<?php
header("Content-Type:text/css");
$color = "#f0f"; // Change your Color Here
$secondColor = "#ff8"; // Change your Color Here

function checkhexcolor($color){
    return preg_match('/^#[a-f0-9]{6}$/i', $color);
}

if (isset($_GET['color']) AND $_GET['color'] != '') {
    $color = "#" . $_GET['color'];
} 

if (!$color OR !checkhexcolor($color)) {
    $color = "#336699";
}


function checkhexcolor2($secondColor){
    return preg_match('/^#[a-f0-9]{6}$/i', $secondColor);
}

if (isset($_GET['secondColor']) AND $_GET['secondColor'] != '') {
    $secondColor = "#" . $_GET['secondColor'];
}

if (!$secondColor OR !checkhexcolor2($secondColor)) {
    $secondColor = "#336699";
}
?>

.btn--base, .btn--base:hover, body::-webkit-scrollbar-thumb, .scroll-to-top, .hero__radar [class*="dot-"], .hero__radar [class*="dot-"]::before, .hero__radar [class*="dot-"]::after, .choose-card__icon, .package-card__feature-list li::before, .package-card.popular-package::after, .post-share li a:hover, .blog-sidebar .title::after, .subscribe-form .subscribe-btn, .d-widget__icon::after, .social-link-list li a:hover, .contact-social-links li::before, .btn-outline--base:hover, .custom--accordion .accordion-button:not(.collapsed), .custom--table thead, .preloader__box [class*="line-"], body.lightmode .pagination .page-item.active .page-link{
    background-color: <?php echo $color; ?>;
}

.page-scroll-bar::-webkit-progress-value {
    background-color: <?php echo $color; ?>;
}

.page-scroll-bar::-moz-progress-bar{
    background-color: <?php echo $color; ?>;
}

.bg--base, {
    background-color: <?php echo $color; ?>; !important
}

.text--base, .page-breadcrumb li:first-child::before, .header .main-menu li a:hover, .header .main-menu li a:focus, .main-menu li.active > a, .cmn-list li::before, .preloader__sitename, .lightmode .preloader__sitename{
    color: <?php echo $color; ?>;
}

.section-subtitle, .header .main-menu li.menu_has_children:hover > a::before{
    color: <?php echo $color; ?>; !important;
}

.overview-card::after, .overview-item:nth-of-type(4n + 1) .overview-card::after{
    border: 1px solid <?php echo $color; ?>;
}

.overview-card__icon ::before, .ratings i{
    color: <?php echo $color; ?>; !important;
}

.btn-outline--base{
    color: <?php echo $color; ?>;
    border: 1px solid <?php echo $color; ?>;
}

.section-subtitle, .custom--accordion-two .accordion-button:not(.collapsed), .header .site-logo.site-title, .hero__top-title, .inline-menu li a:hover, .map-icon i, .contact-info-list .contact-info-single i, .contact-info-list .contact-info-single a:hover, a:hover{
    color: <?php echo $color; ?>;
}

.form--control:focus, 
.d-widget:hover,
.choose-card:hover {
    border-color: <?php echo $color; ?>;
}

.header .main-menu li .sub-menu,
.header.menu-fixed .header__bottom,
.footer,
.lightmode .custom--table thead {
    background-color: <?php echo $secondColor; ?>;
}
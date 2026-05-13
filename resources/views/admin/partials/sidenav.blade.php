<div class="sidebar bg--dark">
    <button class="res-sidebar-close-btn"><i class="las la-times"></i></button>
    <div class="sidebar__inner">
        <div class="sidebar__logo">
            <a href="{{route('admin.dashboard')}}" class="sidebar__main-logo"><img src="{{getImage(getFilePath('logoIcon') .'/logo.png')}}" alt="@lang('image')"></a>
        </div>

        <div class="sidebar__menu-wrapper" id="sidebar__menuWrapper">
            <ul class="sidebar__menu">
                <li class="sidebar-menu-item {{menuActive('admin.dashboard')}}">
                    <a href="{{route('admin.dashboard')}}" class="nav-link ">
                        <i class="menu-icon las la-home"></i>
                        <span class="menu-title">@lang('Dashboard')</span>
                    </a>
                </li>

                <li class="sidebar__menu-header">@lang('Roles')</li> 

                <li class="sidebar-menu-item sidebar-dropdown">
                    <a href="javascript:void(0)" class="{{menuActive('admin.roles*',3)}}">
                        <i class="menu-icon las la-users"></i>
                        <span class="menu-title">@lang('Roles & Permission')</span>
                    </a>
                    <div class="sidebar-submenu {{menuActive('admin.roles*',2)}} ">
                        <ul>
                            <li class="sidebar-menu-item {{menuActive('admin.roles.index')}} ">
                                <a href="{{route('admin.roles.index')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Roles')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.roles.permissions')}} ">
                                <a href="{{route('admin.roles.permissions')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Permissions')</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="sidebar__menu-header">@lang('Users')</li> 
                
                <li class="sidebar-menu-item sidebar-dropdown">
                    <a href="javascript:void(0)" class="{{menuActive('admin.users*',3)}}">
                        <i class="menu-icon las la-users"></i>
                        <span class="menu-title">@lang('Manage Users')</span>
                    </a>
                    <div class="sidebar-submenu {{menuActive('admin.users*',2)}} ">
                        <ul>
                            <li class="sidebar-menu-item {{menuActive('admin.users.active')}} ">
                                <a href="{{route('admin.users.active')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Active Users')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.users.banned')}} ">
                                <a href="{{route('admin.users.banned')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Banned Users')</span>
                                    @if($bannedUsersCount)
                                        <span class="menu-badge pill bg--danger ms-auto">{{$bannedUsersCount}}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.users.employees')}} ">
                                <a href="{{route('admin.users.employees')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Employee')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.users.index')}} ">
                                <a href="{{route('admin.users.index')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('All Users')</span>
                                </a>
                            </li>

                        </ul>
                    </div>
                </li>

                <li class="sidebar__menu-header">@lang('Courses')</li>
                <li class="sidebar-menu-item {{menuActive('admin.courses.categories.index')}}">
                    <a href="{{route('admin.courses.categories.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Category')</span>
                    </a>
                </li>
                <li class="sidebar-menu-item {{menuActive('admin.courses.index')}}">
                    <a href="{{route('admin.courses.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Courses')</span>
                    </a>
                </li>
                <li class="sidebar-menu-item {{menuActive('admin.analysis-config.index')}}">
                    <a href="{{route('admin.courses.gateway.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Payment Gateway')</span>
                    </a>
                </li>
                

                <li class="sidebar__menu-header">@lang('Config')</li>
                <li class="sidebar-menu-item {{menuActive('admin.zerodha-broker.index')}}">
                    <a href="{{route('admin.zerodha-broker.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Zerodha Broker')</span>
                    </a>
                </li>
                <li class="sidebar-menu-item {{menuActive('admin.symbol-list.index')}}">
                    <a href="{{route('admin.symbol-list.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Symbol List')</span>
                    </a>
                </li>
                <li class="sidebar-menu-item {{menuActive('admin.analysis-config.index')}}">
                    <a href="{{route('admin.analysis-config.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('Analysis Config')</span>
                    </a>
                </li>

                <li class="sidebar__menu-header">@lang('Settings')</li>

                <li class="sidebar-menu-item {{menuActive('admin.setting.index')}}">
                    <a href="{{route('admin.setting.index')}}" class="nav-link">
                        <i class="menu-icon las la-life-ring"></i>
                        <span class="menu-title">@lang('General Setting')</span>
                    </a>
                </li>
                {{-- <li class="sidebar-menu-item {{ menuActive('admin.cron*') }}">
                    <a href="{{ route('admin.cron.index') }}" class="nav-link">
                        <i class="menu-icon las la-clock"></i>
                        <span class="menu-title">@lang('Cron Job Setting')</span>
                    </a>
                </li> --}}

                {{-- <li class="sidebar-menu-item {{menuActive('admin.setting.system.configuration')}}">
                    <a href="{{route('admin.setting.system.configuration')}}" class="nav-link">
                        <i class="menu-icon las la-cog"></i>
                        <span class="menu-title">@lang('System Configuration')</span>
                    </a>
                </li> --}}

                <li class="sidebar-menu-item {{menuActive('admin.setting.logo.icon')}}">
                    <a href="{{route('admin.setting.logo.icon')}}" class="nav-link">
                        <i class="menu-icon las la-images"></i>
                        <span class="menu-title">@lang('Logo & Favicon')</span>
                    </a>
                </li>

                {{-- <li class="sidebar-menu-item {{menuActive('admin.extensions.index')}}">
                    <a href="{{route('admin.extensions.index')}}" class="nav-link">
                        <i class="menu-icon las la-cogs"></i>
                        <span class="menu-title">@lang('Extensions')</span>
                    </a>
                </li> --}}

                <li class="sidebar-menu-item  {{menuActive(['admin.language.manage','admin.language.key'])}}">
                    <a href="{{route('admin.language.manage')}}" class="nav-link"
                       data-default-url="{{ route('admin.language.manage') }}">
                        <i class="menu-icon las la-language"></i>
                        <span class="menu-title">@lang('Language') </span>
                    </a>
                </li>

                {{-- <li class="sidebar-menu-item {{menuActive('admin.seo')}}">
                    <a href="{{route('admin.seo')}}" class="nav-link">
                        <i class="menu-icon las la-globe"></i>
                        <span class="menu-title">@lang('SEO Manager')</span>
                    </a>
                </li> --}}

                {{-- <li class="sidebar-menu-item sidebar-dropdown">
                    <a href="javascript:void(0)" class="{{menuActive('admin.setting.notification*',3)}}">
                        <i class="menu-icon las la-bell"></i>
                        <span class="menu-title">@lang('Notification Setting')</span>
                    </a>
                    <div class="sidebar-submenu {{menuActive('admin.setting.notification*',2)}} ">
                        <ul>
                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.global')}} ">
                                <a href="{{route('admin.setting.notification.global')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Global Template')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.email')}} ">
                                <a href="{{route('admin.setting.notification.email')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Email Setting')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.sms')}} ">
                                <a href="{{route('admin.setting.notification.sms')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('SMS Setting')</span>
                                </a>
                            </li>

                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.push')}} ">
                                <a href="{{route('admin.setting.notification.push')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Push Notification Setting')</span>
                                </a>
                            </li>

                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.telegram')}} ">
                                <a href="{{route('admin.setting.notification.telegram')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Telegram Setting')</span>
                                </a>
                            </li>

                            <li class="sidebar-menu-item {{menuActive('admin.setting.notification.templates')}} ">
                                <a href="{{route('admin.setting.notification.templates')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Notification Templates')</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li> --}}

                {{-- <li class="sidebar__menu-header">@lang('Frontend Manager')</li> --}}

                {{-- <li class="sidebar-menu-item {{menuActive('admin.frontend.templates')}}">
                    <a href="{{route('admin.frontend.templates')}}" class="nav-link ">
                        <i class="menu-icon la la-html5"></i>
                        <span class="menu-title">@lang('Manage Templates')</span>
                    </a>
                </li> --}}

                <li class="sidebar-menu-item {{menuActive('admin.frontend.manage.*')}}">
                    <a href="{{route('admin.frontend.manage.pages')}}" class="nav-link ">
                        <i class="menu-icon la la-list"></i>
                        <span class="menu-title">@lang('Manage Pages')</span>
                    </a>
                </li>

                <li class="sidebar-menu-item sidebar-dropdown">
                    <a href="javascript:void(0)" class="{{menuActive('admin.frontend.sections*',3)}}">
                        <i class="menu-icon la la-puzzle-piece"></i>
                        <span class="menu-title">@lang('Manage Section')</span>
                    </a>
                    <div class="sidebar-submenu {{menuActive('admin.frontend.sections*',2)}} ">
                        <ul>
                            @php
                               $lastSegment =  collect(request()->segments())->last();
                            @endphp
                            @foreach(getPageSections(true) as $k => $secs)
                                @if($secs['builder'])
                                    <li class="sidebar-menu-item  @if($lastSegment == $k) active @endif ">
                                        <a href="{{ route('admin.frontend.sections',$k) }}" class="nav-link">
                                            <i class="menu-icon las la-dot-circle"></i>
                                            <span class="menu-title">{{__($secs['name'])}}</span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </li>

                <li class="sidebar__menu-header">@lang('Extra')</li>


                <li class="sidebar-menu-item {{menuActive('admin.maintenance.mode')}}">
                    <a href="{{route('admin.maintenance.mode')}}" class="nav-link">
                        <i class="menu-icon las la-robot"></i>
                        <span class="menu-title">@lang('Maintenance Mode')</span>
                    </a>
                </li>

                <li class="sidebar-menu-item {{menuActive('admin.setting.cookie')}}">
                    <a href="{{route('admin.setting.cookie')}}" class="nav-link">
                        <i class="menu-icon las la-cookie-bite"></i>
                        <span class="menu-title">@lang('GDPR Cookie')</span>
                    </a>
                </li>

                <li class="sidebar-menu-item sidebar-dropdown">
                    <a href="javascript:void(0)" class="{{menuActive('admin.system*',3)}}">
                        <i class="menu-icon la la-server"></i>
                        <span class="menu-title">@lang('System')</span>
                    </a>
                    <div class="sidebar-submenu {{menuActive('admin.system*',2)}} ">
                        <ul>
                            <li class="sidebar-menu-item {{menuActive('admin.system.info')}} ">
                                <a href="{{route('admin.system.info')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Application')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.system.server.info')}} ">
                                <a href="{{route('admin.system.server.info')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Server')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.system.optimize')}} ">
                                <a href="{{route('admin.system.optimize')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Cache')</span>
                                </a>
                            </li>
                            <li class="sidebar-menu-item {{menuActive('admin.system.update')}} ">
                                <a href="{{route('admin.system.update')}}" class="nav-link">
                                    <i class="menu-icon las la-dot-circle"></i>
                                    <span class="menu-title">@lang('Update')</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                {{-- <li class="sidebar-menu-item {{menuActive('admin.users.getuserEnquiry')}}">
                    <a href="{{route('admin.users.getuserEnquiry')}}" class="nav-link">
                        <i class="menu-icon lab la-css3-alt"></i>
                        <span class="menu-title">@lang('User Enquiry')</span>
                    </a>
                </li> --}}

                {{-- <li class="sidebar-menu-item {{menuActive('admin.setting.custom.css')}}">
                    <a href="{{route('admin.setting.custom.css')}}" class="nav-link">
                        <i class="menu-icon lab la-css3-alt"></i>
                        <span class="menu-title">@lang('Custom CSS')</span>
                    </a>
                </li> --}}

                {{-- <li class="sidebar-menu-item  {{menuActive('admin.request.report')}}">
                    <a href="{{route('admin.request.report')}}" class="nav-link"
                       data-default-url="{{ route('admin.request.report') }}">
                        <i class="menu-icon las la-bug"></i>
                        <span class="menu-title">@lang('Report & Request') </span>
                    </a>
                </li> --}}
            </ul>
            {{-- <div class="text-center mb-3 text-uppercase">
                <span class="text--primary">{{__(systemDetails()['name'])}}</span>
                <span class="text--success">@lang('V'){{systemDetails()['version']}} </span>
            </div> --}}
        </div>
    </div>
</div>
<!-- sidebar end -->

@push('script')
    <script>
        if($('li').hasClass('active')){
            $('#sidebar__menuWrapper').animate({
                scrollTop: eval($(".active").offset().top - 320)
            },500);
        }
    </script>
@endpush

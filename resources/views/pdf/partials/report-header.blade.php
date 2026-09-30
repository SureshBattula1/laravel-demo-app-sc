        <div class="header-wrap">
            <div class="header-corner">
                <svg width="120" height="72" viewBox="0 0 120 72" xmlns="http://www.w3.org/2000/svg">
                    <path d="M40 0 C80 0 120 20 120 72 L0 72 L0 40 Z" fill="#0F3CC9" opacity="0.12"/>
                    <path d="M80 0 C110 0 120 30 120 72 L60 72 Z" fill="#0F3CC9" opacity="0.18"/>
                </svg>
            </div>
            <table class="header-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="logo-cell">
                        @if(!empty($school['logo_data_uri']))
                            <img src="{{ $school['logo_data_uri'] }}" class="logo-img" alt="School logo">
                        @endif
                    </td>
                    <td>
                        <p class="school-name">{{ $school['name'] }}</p>
                        @if(!empty($school['branch_line']))
                            <p class="school-branch">{{ strtoupper($school['branch_line']) }}</p>
                        @endif
                        <p class="school-tagline">{{ $school['tagline'] }}</p>
                    </td>
                    <td style="width: 38%;">
                        <div class="address-block">
                            @foreach($school['address_lines'] as $line)
                                {{ $line }}@if(!$loop->last)<br>@endif
                            @endforeach
                            @if(empty($school['address_lines']))
                                —
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>

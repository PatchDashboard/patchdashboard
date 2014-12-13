#! /usr/bin/expect

set timeout 20
set server [lindex $argv 0]
set sndr_mail [lindex $argv 1]
set rcpt_mail [lindex $argv 2]
set mail_subj [lindex $argv 3]
set mail_body [lindex $argv 4]

spawn telnet $server 25

expect "Connected to "
expect "220 "
send "HELO $server\n"
expect "250 "
send "MAIL FROM:<$sndr_mail>\n"
expect "250 "
send "RCPT TO:<$rcpt_mail>\n"
expect "250 "
send "DATA\n"
expect "354 "
send "From:$sndr_mail\n"
send "To:$rcpt_mail\n"
send "Subject:$mail_subj\n\n"
send "$mail_body\n"
send ".\n"
expect "250 "
send "quit\n"
expect "221 "


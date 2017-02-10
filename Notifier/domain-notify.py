#!/bin/python
# -*- coding: utf-8 -*-

#Скрипт отправляет пользователям уведомления о том, что домен скоро закончится/удалится.

import subprocess
import re
import string
import MySQLdb
from xml.dom.minidom import parseString
from datetime import date, datetime, timedelta
from smtplib import SMTP
from email.mime.text import MIMEText

MGRCTL_PATH = '/usr/local/mgr5/sbin/mgrctl'
DOMAIN_NAME_REGEX = '\((.+?)\)';
DBMS_HOST = 'localhost'
DBMS_USER = 'root'
DBMS_USER_PASSWD = ''
DB_NAME = 'billmgr'
GET_EMAIL_QUERY = "SELECT u.email FROM (account AS a JOIN subaccount AS sa ON a.id = sa.account AND sa.id = {0} ) JOIN user AS u ON u.account = a.id"

NOTIFY_EXPIRE_DELTAS = [60, 30, 14, 7, 3, 2, 1]
NOTIFY_DELETE_DELTAS = [0, -14, - 21, -29]

EMAIL_TEMPLATE = """
<html>
<body>
<div bgcolor="#ffffff">
		<div>
		<table width="90%" align="center" cellpadding="0" cellspacing="0" border="0">
		<tbody><tr><td bgcolor="#e5e5e5">
		<div><b>{0}</b></div>
		</td></tr>
		<tr><td>
			<div>
				<p>Здравствуйте, администратор домена <a target="_blank" href="http://{1}">{1}</a>!</p>
				<p>Уведомляем, что {0}{2}.</p>
                                <p>Пополнить лицевой счёт и оплатить продление домена вы можете в <a target="_blank" href="https://panel.parking.by/">Личном кабинете</a>.</p>
				
				<p>--<br>
                                Желаем успехов!<br>
                                Команда "Parking.by"<br>
                                +375 33 633-11-11<br>
                                +375 29 596-85-25<br>
                                <a href="mailto:info@parking.by">info@parking.by</a>
                                </p>
			</div>
		</td></tr>
		</tbody></table>
		</div>
</div>
</body>
</html>
"""

EXPIRE_SOON_TEMPLATE = "срок регистрации домена {0} истекает {1}"
EXPIRE_SOON_ADD = ", функционирование домена будет приостановлено"
DELETE_SOON_TEMPLATE = "домен {0} будет удален из реестра {1}"

EMAIL_FROM = "info@parking.by"
SMTP_SERV_ADDR = "127.0.0.1"
SMTP_USERNAME = "info@parking.by"
SMTP_PASSWD = ""

def get_users():
    users_xml = subprocess.check_output(MGRCTL_PATH + ' -m billmgr -o xml user')

def get_domains():
    domains_xml = subprocess.check_output(MGRCTL_PATH + ' -m billmgr -o xml domain su=snarovivan', shell = True)
    dom_domains_xml = parseString(domains_xml)

    domains = []
    for elem in dom_domains_xml.getElementsByTagName('elem'):
        domain = {}
        
        #обрабатываем только активные и остановленные домены
        status = elem.getElementsByTagName('status')[0].childNodes[0].nodeValue
        if '2' != status and '3' != status:
			continue
	        
        name_raw = elem.getElementsByTagName('domain')[0].childNodes[0].nodeValue
        #match = re.search(DOMAIN_NAME_REGEX, name_raw)
        #if match:
        #    domain['name'] = match.group(1)
		domain['name'] = name_raw.encode('utf-8')
        expire_date_raw = elem.getElementsByTagName('expiredate')[0].childNodes[0].nodeValue
        domain['expire_date'] = datetime.strptime(expire_date_raw, '%Y-%m-%d').date()

        subaccount =  elem.getElementsByTagName('subaccount')[0].childNodes[0].nodeValue
        db = MySQLdb.connect(host = DBMS_HOST, user = DBMS_USER, passwd = DBMS_USER_PASSWD, db = DB_NAME)
        cur = db.cursor()
        cur.execute(GET_EMAIL_QUERY.format(subaccount))
        domain['owner_email'] = cur.fetchone()[0]
        db.close()

        domains.append(domain)

    return domains


domains = get_domains()
cur_date = date.today()
smtp_serv = SMTP(SMTP_SERV_ADDR)
smtp_serv.login(SMTP_USERNAME, SMTP_PASSWD)
for domain in domains:
    days_remaining = (domain['expire_date'] - cur_date).days

    if days_remaining in NOTIFY_EXPIRE_DELTAS:
        subject = EXPIRE_SOON_TEMPLATE.format(domain['name'], domain['expire_date'])
        mail_text = EMAIL_TEMPLATE.format(subject, domain['name'], EXPIRE_SOON_ADD)
        msg = MIMEText(mail_text, 'html')
        msg['Subject'] = subject
        msg['From'] = "Parking.by <%s>" % EMAIL_FROM
        msg['To'] = domain['owner_email']
        
        smtp_serv.sendmail(EMAIL_FROM, domain['owner_email'], msg.as_string())
    elif days_remaining in NOTIFY_DELETE_DELTAS:
        subject = DELETE_SOON_TEMPLATE.format(domain['name'], domain['expire_date'] + timedelta(days=30))
        mail_text = EMAIL_TEMPLATE.format(subject, domain['name'], '')
        msg = MIMEText(mail_text, 'html')
        msg['Subject'] = subject
        msg['From'] = "Parking.by <%s>" % EMAIL_FROM
        msg['To'] = domain['owner_email']

        smtp_serv.sendmail(EMAIL_FROM, domain['owner_email'], msg.as_string())
        
smtp_serv.quit()

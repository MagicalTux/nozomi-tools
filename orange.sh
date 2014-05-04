if [ ! -f /dev/ppp ]; then
        mknod /dev/ppp c 108 0
fi
# GSM
#pppd connect 'chat -e -v "" AT OK ATDT+33674501100 CONNECT' /dev/rfcomm0 9600 login user orange crtscts nodetach debug defaultroute
# GPRS
#pppd connect 'chat -e -v -f /root/orange.chat' /dev/noz0 9600 login user orange crtscts nodetach debug defaultroute
pppd connect 'chat -e -v -f /root/orange.chat' /dev/noz0 3600000 login user orange.fr crtscts nodetach debug usepeerdns nolock defaultroute



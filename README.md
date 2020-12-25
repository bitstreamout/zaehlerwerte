# zaehlerwerte -- PHP script to get readings from electric meters

This simple PHP script is mostly for german owners of photovoltaics power system.
The script is used to get readings of **three** electric meters handled by the
middleware server from volkszaehler https://github.com/volkszaehler/volkszaehler.org/.

The configuration is done in the header of the script **zaehlerwerte.php** that is
the three **UUID** values of the volume of the purchased electricity, as well as the
solar power input, both from/to the electricity supplier, and finally the own solar
power generation.

With help of those **UUID** the script asks the middleware server for the **raw**
data at the specified date and calculates the own consumption from the solar power
generation as well as the overall consumption.

The configuration of the URL of the middleware server depends on the used type of
httpd server.  The active configuration is plain php on port 8080 as well as the
wrapper script **zaehlerwerte.cgi** and **index.html** for a simple thttpd.

Enjoy

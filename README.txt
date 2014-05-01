Memcached Cluster Cache Store
Version: 2.7.0
Moodle version: 2.5.0 and up (tested through 2.7)
Maintainer: Eric Merrill (merrill@oakland.edu)


This plugin implements a modified version of the standard Memcached cache. Specifically it allows multiple, distributed, memcached stores to stay in sync with one another.

The most common use case for this is when using a load balancer, you can create memcached services on each front end server, which allows for very fast fetches. The tradeoff is that when updating the cache, the update will have to be performed against every front end server, which is comparatively slow. Some Moodle caches, such as Language Strings or Database Schema, have a very high number of fetches compared to sets, which makes them prime for this type of cache.


Setup:
Fetch servers:
The list of servers to retrieve data from, in hostname:port:weight format (port and weight are optional). Most commonly, this will be set to ‘localhost’, which means that each front end server is looking at itself.

Set servers:
The list of servers that modifications should be made on, in hostname:port format (port is optional). Note that servers listed in Fetch servers ARE NOT updated, and must be specifically called out in Set servers. In the common case of Fetch servers being set to ‘localhost’, this would be a list of the full names of all servers, such as:
fe01.example.com
fe02.example.com
fe03.example.com

All other settings are the same as the standard Memcached plugin


Risk:
If there is intermittent network connectivity between the front ends, data can become out of sync, which can cause unexpected results. For this reason, you should only use this in a known, stable, network environment, and purge all caches if you think there has been a network disruption.
Individual system restarts, or general unavailability are not a problem, as long the memcached service on the system was restarted.



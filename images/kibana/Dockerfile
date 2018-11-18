# escape=\
ARG BASE_IMAGE
FROM ${BASE_IMAGE}

#This is prod-like image where all dashboards definitions are included into
#an image. You only have to provide configuration at runtine (e.g. indices
#names).

MAINTAINER Konrad Otrębski

ADD templates /usr/share/otrebski/templates
ADD entrypoint.sh /usr/share/otrebski/entrypoint.sh

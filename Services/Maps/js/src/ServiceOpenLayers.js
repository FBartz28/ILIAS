/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ******************************************************************** */

import View from 'ol/View';
import Map from 'ol/Map';
import TileLayer from 'ol/layer/Tile';
import OSM from 'ol/source/OSM';
import { defaults as control } from 'ol/control';
import FullScreen from 'ol/control/FullScreen';
import { transform } from 'ol/proj';
import Overlay from 'ol/Overlay';

export default class ServiceOpenLayers {
  /**
     * Create a ServiceOpenLayers object.
     *
     * @param 	{object} 	j_query
     * @param 	{array} 	map_data
     * @param 	{string} 	invalid_address
     * @param 	{array} 	user_markers
     */
  constructor(j_query, invalid_address, map_data, user_markers) {
    this.j_query = j_query;
    this.invalid_address = invalid_address;
    this.map_data = map_data;
    this.user_markers = user_markers;
    this.views = [];
    this.map = null;
  }

  /**
     * Initialize values
     *
     * @param {object} ol_map_data
     * @return {void}
     */
  init(ol_map_data) {
    for (const id in ol_map_data) {
      const map = ol_map_data[id];
      const zoom = map[2];
      const central_marker = map[3];
      const replace_marker = map[5];
      const geo = map[7];
      const pos = this.posToOSM([map[1], map[0]]);

      this.geolocationURL = geo;

      this.initView(id, pos, zoom);
      this.initMap(id, replace_marker);
      this.initUserMarkers(id, pos, replace_marker, central_marker);
    }
  }

  /**
     * Initialize the view array.
     *
     * @param {string} id
     * @param {array} pos
     * @param {number} zoom
     * @return {void}
     */
  initView(id, pos, zoom) {
    this.views[id] = new View({
      center: pos,
      maxZoom: 18,
      minZoom: 0,
      zoom,
    });

    // Bind the maps zoom level to the select box zoom.
    this.views[id].on('propertychange', (e) => {
      if (e.key === 'resolution') {
        $(`#${id}_zoom`).val(Math.floor(this.views[id].getZoom()));
      }
    });
  }

  /**
     * Initialise a map on the page.
     *
     * @param 	{string} 	id
     * @param 	{boolean} 	replace_marker
     * @return 	{void}
     */
  initMap(id, replace_marker) {
    this.map = new Map({
      layers: [
        new TileLayer({
          preload: 4,
          source: new OSM(),
        }),
      ],
      target: id,
      controls: new control().extend([
        new FullScreen(),
      ]),
      loadTilesWhileAnimating: true,
      view: this.views[id],
    });

    this.map.on('click', (e) => {
      e.preventDefault();
      const center = e.coordinate;
      this.jumpTo(id, center);
      if (replace_marker) {
        this.deleteAllMarkers(id);
        this.setMarker(id, center);
      }
      this.updateInputFields(id, center);
    });
  }

  /**
     * Init the user_markers array.
     *
     * @param 	{string} 	id
     * @param 	{number} 	pos
     * @param 	{boolean} 	replace_marker
     * @param 	{boolean} 	central_marker
     * @returns {void}
     */
  initUserMarkers(id, pos, replace_marker, central_marker) {
    if (replace_marker || central_marker) {
      this.deleteAllMarkers(id);
      this.setMarker(id, pos);
      return;
    }

    // Only for participants overview.
    // Navigation is managed by participants-buttons here.
    this.map.removeEventListener('click');
    const mapUserMarkers = this.user_markers[id];
    for (const cnt in mapUserMarkers) {
      const userMarkerData = mapUserMarkers[cnt];
      const pos = this.posToOSM([userMarkerData[0], userMarkerData[1]]);
      this.user_markers[cnt] = [pos, userMarkerData[2]];
      this.setMarker(id, pos, userMarkerData[2]);
    }
  }

  /**
     * Transform a coordinate from OSM projection to human readable projection.
     *
     * @param 	{array} pos 	[longitude, latitude]
     * @return 	{array} 		[longitude, latitude]
     */
  posToHuman(pos) {
    return transform(pos, 'EPSG:3857', 'EPSG:4326');
  }

  /**
     * Transform a coordinate from human readable projection to OSM projection.
     *
     * @param 	{array} pos 	[longitude, latitude]
     * @return 	{array} 		[longitude, latitude]
     */
  posToOSM(pos) {
    return transform(pos, 'EPSG:4326', 'EPSG:3857');
  }

  /**
     * Jump to a position on the map.
     *
     * @param 	{string} 	id
     * @param 	{array} 	pos 	[longitude, latitude]
     * @param 	{number} 	zoom
     * @return 	{void}
     */
  jumpTo(id, pos, zoom) {
    this.views[id].animate({
      center: pos,
      duration: 2000,
      zoom,
    });
  }

  /**
     * Looks up for an user given Address.
     *
     * @param 	{string} id
     * @param 	{string} address
     * @return 	{void}
     */
  jumpToAddress(id, address) {
    $(`#${id}_addr`).attr('disabled', 'disabled');
    $(`#${id}_lng`).attr('disabled', 'disabled');
    $(`#${id}_lat`).attr('disabled', 'disabled');

    $.ajax({
      url: this.geolocationURL.replace('[QUERY]', address),
      data: {},
      dataType: 'json',
    }).done((function (module) {
      return function (data) {
        if (data.length === 0) {
          $(`#${id}_addr`).val(module.addressInvalid);
          return;
        }
        const lon = parseFloat(data[0].lon, 10);
        const lat = parseFloat(data[0].lat, 10);

        const pos = module.posToOSM([lon, lat]);

        module.jumpTo(id, pos, 16);
        module.deleteAllMarkers(id);
        module.setMarker(id, pos);
        module.updateInputFields(id, pos, address);
      };
    })(this))
      .fail(() => {
        $(`#${id}_address`).val('');
        alert('Could not connect to reverse geo location server. Please contact an administrator of the ILIAS installation.');
      })
      .always(() => {
        $(`#${id}_addr`).removeAttr('disabled');
        $(`#${id}_lng`).removeAttr('disabled');
        $(`#${id}_lat`).removeAttr('disabled');
      });
  }

  /**
     * Force throwing a resize event.
     *
     * @return 	{void}
     */
  forceResize() {
    $('input[onclick*="il.Form.showSubForm"]').each(function () {
      const e = $(this);
      e.attr(
        'onclick',
        `${e.attr('onclick')};window.dispatchEvent(new Event('resize'));`,
      );
    });
  }

  /**
     * Set a marker at the given position at the map.
     *
     * @param 	{string} 	id
     * @param 	{array} 	pos 	[longitude, latitude]
     * @return 	{void}
     */
  setMarker(id, pos) {
    const container = document.getElementById(id);
    if(container) {
      const element = document.createElement('div');
      element.className = 'marker';
      container.appendChild(element);
      const marker = new Overlay({
        element,
      });
      marker.setOffset([-7.5, -23.5]);
      marker.setPosition(pos);
      this.map.addOverlay(marker);
      element.innerHTML = "<img src='./Services/Maps/images/mm_20_blue.png'>";
    }
  }

  /**
     * Remove all child elements.
     *
     * @param {string} 	id
     * @returns {void}
     */
  deleteAllMarkers(id) {
    const container = document.getElementById(id); 
    if (container) {
      const marker = container.querySelectorAll('.marker');
      for (let i = 0; i < marker.length; i++) {
        marker[i].remove();
      }
    }
  }

  /**
     * Move to a user marker and open popup.
     *
     * @param 	{string} id
     * @param 	{number} j 	Counter for user_markers.
     * @returns {void}
     */
  moveToUserMarkerAndOpen(id, j) {
    const user_marker = this.user_markers[id][j];
    if (user_marker) {
      const pos = this.posToOSM([user_marker[0], user_marker[1]]);
      this.deleteAllPopups();
      this.jumpTo(id, pos, 16);
      this.setPopup(id, pos, user_marker[2]);
    } else {
      console.log(`No user marker no. ${j} for map ${id}`);
    }
  }

  /**
     * Set a popup window to pos.
     *
     * @param 	{string} 	id
     * @param 	{array} 	pos
     * @param 	{string} 	elem 	Can hold html or pure text.
     * @returns {void}
     */
  setPopup(id, pos, elem) {
    const container = document.getElementById(id);
    if(container) {
      const append = document.createElement('div');
      append.className = 'arrow_box';
      append.addEventListener('click', (function (module) {
        return function () {
          module.deleteAllPopups();
        };
      }(this)));
      append.innerHTML = elem;
      container.appendChild(append);

      const popup = new Overlay({
        element: append,
        insertFirst: false,
      });
      popup.setOffset([15.5, -53.5]);
      popup.setPosition(pos);
      this.map.addOverlay(popup);
    }
  }

  /**
     * Delete all popups with class arrow_box.
     *
     * @returns 	{void}
     */
  deleteAllPopups() {
    const popups = document.getElementsByClassName('arrow_box');
    for (let i = 0; i < popups.length; i++) {
      popups[i].remove();
    }
  }

  /**
     * Update the longitude, latitude and the zoom of the map.
     *
     * @param 	{string} id
     * @return 	{void}
     */
  updateMap(id) {
    const lat = parseFloat($(`#${id}_lat`).val());
    const lon = parseFloat($(`#${id}_lng`).val());
    const zoom = $(`#${id}_zoom`).val();
    const pos = this.posToOSM([lon, lat]);

    // this.updateMarkers(id);
    this.views[id].setZoom(zoom);
    this.jumpTo(id, pos);
    this.updateInputFields(id, pos);
  }

  /**
     * Update the input fields.
     *
     * @param 	{string} 	id
     * @param 	{array} 	pos 	[longitude, latitude]
     * @param 	{string} 	address
     * @return 	{void}
     */
  updateInputFields(id, pos, address) {
    address = address || 'undefined';
    const human_pos = this.posToHuman(pos);
    $(`#${id}_addr`).val(address);
    $(`#${id}_lng`).val(human_pos[0]);
    $(`#${id}_lat`).val(human_pos[1]);
  }
}

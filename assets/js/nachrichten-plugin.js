function validationFocus(id) {
				      document.getElementById(id).style.border = "1px solid #ddd";
				      document.getElementById(id).style.boxShadow = "0 1px 2px rgba(0, 0, 0, 0.07) inset";
}
function validateKeyword()
{
  var newsplugin_keywords = document.getElementById('newsplugin_keywords');
  var newsplugin_keywords_value = document.getElementById('newsplugin_keywords').value.toLowerCase();
  var keyword_suggestion = document.getElementById('keyword_suggestion');
  var or = newsplugin_keywords_value.indexOf(" or ");
  var and = newsplugin_keywords_value.indexOf(" and ");
  var comma = newsplugin_keywords_value.indexOf(",");
  var suggestion = '';
  if (or > 0 || and > 0 || comma > 0) {
	newsplugin_keywords.style.border = "1px solid #ff0000";
	newsplugin_keywords.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
	suggestion = "<span style='color:red;'>You are using an invalid syntax.<br>Please consider using the suggestion below:</span><br>";
	var text = newsplugin_keywords_value.replace(/ or /g, " | ");
	text = text.replace(/ and /g, " & ");
	text = text.replace(/,/g, " | ");
	suggestion += "<span style='color:#000;font-weight:bold;font-style:normal;'>" + text + "</span>";
	suggestion += "<br><br><p style='font-style:normal;font-weight:bold;margin-top:10px'>Keyword Tips:</p>";
	suggestion += "<ul style='margin-top:5px;list-style: inside none disc;'><li><strong>Symbol | stands for OR</strong><br>Using the | symbol gives you articles for every keyword in your search string.</li><li><strong>Symbol &amp; stands for AND</strong><br>Using the &amp; symbol gives you only those articles that contain all keywords in your search string.</li><li><strong>Quotation marks</strong><br>Using quotation marks ' ' limits your search for exact phrases.</li><li><strong>Asterisk sign</strong><br>Using an asterisk sign * gives you variations of the root keyword. You cannot use it in phrases.</li><li><strong>Parenthesis</strong><br>You can use parenthesis ( ) to adjust the priority of your search phrase evaluation (as common math/boolean expressions).</li></ul><br><br>";
  }
  keyword_suggestion.innerHTML = suggestion;
}
function validateShortcode() {
  var newsplugin_title = document.getElementById('newsplugin_title');
  var newsplugin_keywords = document.getElementById('newsplugin_keywords');
  var newsplugin_articles = document.getElementById('newsplugin_articles');
  if (newsplugin_title.value === "" || /^\s*$/.test(newsplugin_title.value) || newsplugin_articles.value === "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
	if (newsplugin_title.value === "" || /^\s*$/.test(newsplugin_title.value)) {
	  newsplugin_title.style.border = "1px solid #ff0000";
	  newsplugin_title.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
	}
	
	if (newsplugin_articles.value === "" || /^\s*$/.test(newsplugin_articles.value) || isNaN(newsplugin_articles.value) || parseInt(newsplugin_articles.value) <= 0) {
	  newsplugin_articles.style.border = "1px solid #ff0000";
	  newsplugin_articles.style.boxShadow = "0 1px 2px rgba(255, 0, 0, 0.07) inset";
	}
	window.scrollTo(0, 0);
	if (!jQuery(".error").length) {
	  jQuery("<div class='error'><p>Fill the required fields properly.</p></div>").insertBefore("#shortcodeTable");
	}
  } else {
	window.scrollTo(0, 0);
	generateShortcode();
	jQuery(".error").hide();
  }
}
function generateShortcode(uid) {
  var shortcode_params = "";
  var owns = Object.prototype.hasOwnProperty;
  var key;
  var bool_opts1 = new Object({newsplugin_more_premium: 'show_premium_only'});
  for (key in bool_opts1) {
	if (owns.call(bool_opts1, key)) {
	  var value = document.getElementById(key).checked;
	  if (value) {
		 shortcode_params += " " + bool_opts1[key] + "='true'";
	  }
	  else
	  {
		  shortcode_params += " " + bool_opts1[key] + "='false'";
	  }
	}
  }
  var str_opts = new Object({newsplugin_title: 'title', newsplugin_partner_id: 'partner_id',  newsplugin_link_open: 'link_open_mode'});
  for (key in str_opts) {
	if (owns.call(str_opts, key)) {
	  var value = document.getElementById(key).value;
	  if (value !== "") {
		shortcode_params += " " + str_opts[key] + "='" + value + "'";
	  }
	}
  }
  var bool_opts = new Object({newsplugin_more_dates: 'show_date', newsplugin_more_abstracts: 'show_abstract',newsplugin_more_image: 'show_image'});
  for (key in bool_opts) {
	if (owns.call(bool_opts, key)) {
	  var value = document.getElementById(key).checked;
	  if (value) {
		shortcode_params += " " + bool_opts[key] + "='true'";
	  }
	  else
	  {
		  shortcode_params += " " + bool_opts[key] + "='false'";
	  }
	}
  }
  var newsplugin_articles = Math.abs(parseInt(document.getElementById('newsplugin_articles').value));
  if (newsplugin_articles !== "" && !isNaN(newsplugin_articles)) {
	shortcode_params += " count='" + newsplugin_articles + "'";
  }

  shortcode_params += " wp_uid='"+uid+"'";
  var html = "<p>Press Ctrl+C to copy to clipboard and paste it in your posts or pages.</p>";
  html += "<p><textarea id='shortcode-field' onfocus='this.select()' onclick='this.select()' readonly='readonly' style='width:400px; height:200px; max-width:400px; max-height:200px; min-width:400px; min-height:200px;'>[nachrichten_plugin_feed id='" + new Date().valueOf() + "'" + shortcode_params + "]</textarea></p>";
  document.getElementById('shortcode-generated').innerHTML = html;
  tb_show("Nachrichten Plugin Shortcode Generated", "#TB_inline?width=410&height=305&inlineId=shortcode-generated");
  document.getElementById('shortcode-field').focus();
  return false;
}
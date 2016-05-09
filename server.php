<?php

	$cache_expiry = 60; // cache expiry interval in minutes. dadat will be deleted if in cache longer than this


	$host        = "host=nc2";
	$port        = "port=5432";
	$dbname      = "dbname=planetosm";
	$credentials = "user=xxxx password=xxxx";


	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, POST');
	header('Access-Control-Allow-Headers: X-Requested-With');
	header('X-Powered-By: Markware GIS');


	
	if ( !isset( $_GET['w'] ) 	)
	{
	
    	header('Error: Unsupported Request', false, 501);
		exit;
	
	}
	else
	{
		$service = strtolower($_GET['w']);
	}

	$debug=false;
	if ( isset( $_GET['debug'] ) 	)
	{
		if ( strtolower($_GET['debug']) == "true")
		{
			$debug = true;
			header('Markware GIS - Debug = True');
			echo "Querystring: " . $_SERVER['QUERY_STRING'] . "\n\n";	
			echo "Service: ". $service . "\n\n";
		}
	}


	if ( isset( $_GET['nocache'] ) 	)
	{
		$cache_expiry = -1; // Disable Caching all together
		header('Markware GIS - Caching Disabled by QueryString');
	}



	// =============== RETURN ONE TILE ========================
	if ( $service == "tile" )	
	{	
				$zoom=$_GET['z'];
				$tile_y=$_GET['y']*1;
				$tile_x=$_GET['x']*1;
				
				
				$cache_key=$zoom . "-" . $tile_y . "-" . $tile_x;
				
				
			
			
				//Tile numbers to lon./lat.[edit]
				$n = pow(2, $zoom);
				$topLon = $tile_x / $n * 360.0 - 180.0;
				$topLat = rad2deg(atan(sinh(pi() * (1 - 2 * $tile_y / $n))));
				$botLon = ($tile_x +1) / $n * 360.0 - 180.0;
				$botLat = rad2deg(atan(sinh(pi() * (1 - 2 * ( $tile_y +1 ) / $n))));
				$srid = "4326";
				$finalsrid = "4326";
			
			
			
				if ( $debug == true)
				{
					echo "tile_x " . $tile_x . ", tile_y " . $tile_y . "\n\n";
					echo $topLon . ", " . $topLat . "\n" . $botLon . ", " . $botLat . "\n\n";
					echo "North " . $N . ", South " . $S . ", East " . $E . ", West " . $W . "\n\n";
				}
			
			
			
			// Sould consider searchoing points for entrances
			
			GetBBoxData($topLon, $topLat, $botLon, $botLat, $srid, $finalsrid, $cache_key);
			
			exit;
	}



	// =============== RETURN BBOX ========================
	if ( $service == "bbox.json" )	
	{	

		$bbox=lcase(trim($_GET['bbox']));
				
		$cache_key="b-" . $bbox;   // probably in a differenttable or do not cache this as it may never be repeated
				
		echo "Querystring: " . $_SERVER['QUERY_STRING'] . "\n\n";	
		echo "Bounding Box Service";
		exit;
	
	}

	// =============== RETURN ONE FEATURE ========================
	if ( $service == "feature" )	
	{	
		// http://gis.stackexchange.com/questions/59487/assigning-a-nearest-polygon-to-a-point
		
		// Note - watch for multi polygons and polygons that realate to the same building
		
		
		echo "Feature Service";
		exit;
	
	}



	// Falls through to an error, invalid service requested

	header('Error: Unsupported Markware GIS Request', false, 501);
	exit;



	// =====================================================================
	// =============== Hit teh Db for a BBOX ===============================
	// =====================================================================
	

		function GetBBoxData($topLon, $topLat, $botLon, $botLat, $srid, $finalsrid, $cachekey)
		{
		
					
					
					global $host, $port, $dbname, $credentials, $debug, $cache_expiry;
					
					$debug=true;
					
					
					$db1 = pg_pconnect( "$host $port $dbname $credentials"  );
					if(!$db1)
					{
					  header('Error: Database Thread 1 Unavailable', false, 503);
					  exit;
					} 


					// ------------------ Cache Lookup and check for expiry -----------
					
									// Check if it is in the cache already
									$sql = "SELECT * from cache_tiles where key = '$cachekey';";
									
									$retc = pg_query($db1, $sql);
									
									if ( pg_num_rows($retc) > 0 ) // so have at least one in the cache
									{
									
										// test for Cache Expiry
										if(strtotime(pg_fetch_result($retc, 0, 'created')) < strtotime("-$cache_expiry minutes")) 
										{
 											// Expired so remove from teh database.
 											// I am not overwriting it to cover the case where it may
 											// existmore thna once in the record
 											// it may be possibel to not delete it and to an update and insert later on
 											// either way, it is still two hits and this method keeps the database tidy
 											
 											$sql = "DELETE from cache_tiles where key = '$cachekey';";
 											$retc = pg_query($db1, $sql);
											header('Markware GIS: Recached due to tile expiry');	
 										}
 										else
 										{
										
											// Not expired so return teh cached Json and exit
											$Returnjson = pg_fetch_result($retc, 0, 'data');
											set_browser_cache();
											header('Content-Type: application/json');
											header('Markware GIS: Served from Cache');							
											echo $Returnjson;
											pg_close($db1);
											exit;
										}
				
									}
					
									

					$db2 = pg_pconnect( "$host $port $dbname $credentials"  );
					if(!$db2)
					{
					  header('Error: Database Thread 2 Unavailable', false, 503);
					  exit;
					} 
					
					
				$properties = 	"	\"osm_id\",	
									\"name\",
									\"type\",
									\"building\",
									\"height\" as \"height\",
									\"tags\"->'building:height' AS \"building:height\",
									
									\"tags\"->'levels' AS \"levels\",
									\"tags\"->'building:levels' AS \"building:levels\",
									
									\"tags\"->'min_height' AS \"min_height\",
									\"tags\"->'building:min_height' AS \"building:min_height\",
									\"tags\"->'min_level' AS \"min_level\",
									\"tags\"->'building:min_level' AS \"building:min_level\",

									\"tags\"->'building:color' AS \"building:color\",
									\"tags\"->'building:colour' AS \"building:colour\",

									\"tags\"->'building:material' AS \"building:material\",
									\"tags\"->'building:facade:material' AS \"building:facade:material\",
									\"tags\"->'building:cladding' AS \"building:cladding\",

									\"tags\"->'roof:color' AS \"roof:color\",
									\"tags\"->'roof:colour' AS \"roof:colour\",
									\"tags\"->'building:roof:color' AS \"building:roof:color\",
									\"tags\"->'building:roof:colour' AS \"building:roof:colour\",

									\"tags\"->'roof:material' AS \"roof:material\",
									\"tags\"->'building:roof:material' AS \"building:roof:material\",
									

									\"tags\"->'roof:shape' AS roofShape,
									\"tags\"->'building:shape'  AS shape,
									\"tags\"->'roof:height' AS roofHeight

								";
					
	


$sqlp = <<<EOF

WITH bbox AS 
  (SELECT ST_Transform(ST_MakeEnvelope($topLon, $topLat ,$botLon , $botLat , $srid), 900913) As geom)
  SELECT row_to_json(fc)
  FROM ( SELECT 'FeatureCollection' As type, array_to_json(array_agg(f)) As features
  FROM (SELECT 'Feature' As type, osm_id As id, 'polys_table' AS source,
    ( SELECT row_to_json(t) 
     FROM (SELECT $properties ) t )As properties,
     ST_AsGeoJSON(ST_Transform (lg.way ,$finalsrid) )::json As geometry 
  FROM planet_osm_polygon As lg, bbox
  WHERE ( "building" is NOT NULL OR "building:part" IS NOT NULL) AND lg.way && bbox.geom
 ) As f 
) fc;  
					

EOF;

$sqll = <<<EOF

WITH bbox AS 
  (SELECT ST_Transform(ST_MakeEnvelope($topLon, $topLat ,$botLon , $botLat , $srid), 900913) As geom)
  SELECT row_to_json(fc)
  FROM ( SELECT array_to_json(array_agg(f)) As features
  FROM (SELECT 'Feature' As type, osm_id As id, 'lines_table' AS source ,
    ( SELECT row_to_json(t) 
     FROM (SELECT $properties ) t )As properties,
     ST_AsGeoJSON(ST_Transform (ST_MakePolygon(ST_AddPoint( lg.way , ST_StartPoint(lg.way))) ,$finalsrid) )::json As geometry 
  FROM planet_osm_line As lg, bbox
  WHERE ( "building" is NOT NULL OR "building:part" IS NOT NULL) AND lg.way && bbox.geom
 ) As f 
) fc; 				

EOF;
					$state1 = 999;	
					$error1 = "Unknown";
					
					 if (!pg_connection_busy($db1)) 
					 {
						if ( pg_send_query($db1, $sqlp) )
						{
						
							$res1 = pg_get_result($db1);						

							  if ($res1) 
							  {
								    $state1 = pg_result_error_field($res1, PGSQL_DIAG_SQLSTATE);
								    $error1 = pg_result_error($res1);
								    
								    if ($state1==0) 
								    {
								      // success
								   	}
								    else 
								    {
									      // some error happened
									      if ($state1=="23505") 
									      { // unique_violation
									        // process specific error
									      }
									      else 
									      {
									       // process other errors
									      }
							    	}
							    }
							    else
							    {
							    $error1="No result Returned .. Weird ..";
							    }
							}  
						}


					$state2 = 999;	
					$error2 = "Unknown";
					
					 if (!pg_connection_busy($db2)) 
					 {
						if ( pg_send_query($db1, $sqll) )
						{
						
							$res2 = pg_get_result($db2);						

							  if ($res2) 
							  {
								    $state2 = pg_result_error_field($res2, PGSQL_DIAG_SQLSTATE);
								    $error2 = pg_result_error($res2);
								    
								    if ($state2==0) 
								    {
								      // success
								   	}
								    else 
								    {
									      // some error happened
									      if ($state2=="23505") 
									      { // unique_violation
									        // process specific error
									      }
									      else 
									      {
									       // process other errors
									      }
							    	}
							    }
							    else
							    {
							    $error2="No result Returned .. Weird ..";
							    }
							}  
						}

					pg_close($db2);




						//$res1 = pg_query($db1, $sqlp);
						//$res2 = pg_query($db2, $sqll);
						
						if( $state1 != 0 or $state2 != 0)
						{
					    	header('Error: Database did not return expected Result', false, 503);
					    	echo "Thread 1: State: ". $state1 . " - " . $error1 . "\n";
					    	echo "Thread 2: State: ". $state2 . " - " . $error2 . "\n\n";
				    		
				    		if ($debug == true )
				    		{
					    		echo $sqlp ."\n\n";
					    		echo $sqll ."\n\n";
					    	}

							exit;

						} 

						$JsonresultP = json_decode(preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '',pg_fetch_row($res1)[0]), true);
												
						
						$JsonresultL = json_decode(preg_replace('/,\s*"[^"]+":null|"[^"]+":null,?/', '',pg_fetch_row($res2)[0]), true);
						
						
						$Returnjson = json_encode(parse_json(array_merge_recursive( $JsonresultP, $JsonresultL)));
						
						//$Returnjson=$JsonresultP;
						
						// Update the Cache. Currebntly, the record is deleted earlier inteh script if it is found and is expired
						// so a straight insert will work
						
						$sql = "INSERT INTO cache_tiles (\"key\", \"created\", \"data\") VALUES ( '$cachekey', 'now' , '" . pg_escape_string($Returnjson) . "');";
						$result = pg_query($db1, $sql);

						// Output Json
						set_browser_cache();
						header('Content-Type: application/json');

						//echo json_encode($Jsonresult);
						echo $Returnjson;
						
						pg_close($db1);		
						
						exit;
						
		   
		}
		
		function set_browser_cache()
		{
			global $cache_expiry;
			
			$seconds_to_cache = $cache_expiry * 60;
			$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
			header("Expires: $ts");
			header("Pragma: cache");
			header("Cache-Control: max-age=$seconds_to_cache");
		
			return;
		}
		
		function parse_json( $r )
		{
			// parse json to determine which tags we actually need and return a final crrect json representation of a buildng
			


			if ( isset ($r['features'] ) )
			{
			
					for($i=0; $i<count($r['features']); $i++) 
					{
					   
					   $b = get_json_property ($r,$i,'osm_id');
					   
					   if ( $b == '265932518' )
					   	{
					   	
					   		jsonencode( $r);
					   		echo 'HERE WE ARE ...\n' . $r;					   		
					   	}
							
							// levels
							
							$b = get_json_property ($r,$i,'levels');
							$c = get_json_property ($r,$i,'building:levels');
							set_json_property( $r, $i, 'levels', ( $b ?: $c ) );
							
							$b = get_json_property ($r,$i,'height');
							$c = get_json_property ($r,$i,'building:height');
							set_json_property( $r, $i, 'height', ( $b ?: $c ) );
							
							
							$b = get_json_property ($r,$i,'min_height');
							$c = get_json_property ($r,$i,'building:min_height');
							set_json_property( $r, $i, 'minHeight', ( $b ?: $c ) );
							
							
							$b = get_json_property ($r,$i,'min_level');
							$c = get_json_property ($r,$i,'building:min_level');
							set_json_property( $r, $i, 'minLevel', ( $b ?: $c ) );
							
							$b = get_json_property ($r,$i,'building:color');
							$c = get_json_property ($r,$i,'building:colour');
							set_json_property( $r, $i, 'color', ( $b ?: $c ) );
							
							
							$b = get_json_property ($r,$i,'building:material');
							$c = get_json_property ($r,$i,'building:facade:material');
							$d = get_json_property ($r,$i,'building:cladding');
							set_json_property( $r, $i, 'material', ( $b ?: $c ?: $d ) );
							
							$b = get_json_property ($r,$i,'roof:color');
							$c = get_json_property ($r,$i,'roof:colour');
							$d = get_json_property ($r,$i,'building:roof:color');
							$e = get_json_property ($r,$i,'building:roof:colour');
							set_json_property( $r, $i, 'roofColor', ( $b ?: $c ?: $d ?: $e ) );

							
							$b = get_json_property ($r,$i,'roof:material');
							$c = get_json_property ($r,$i,'building:roof:material');
							set_json_property( $r, $i, 'roofMaterial', ( $b ?: $c ) );


							set_json_property( $r, $i, 'roofShape', check_shape(strtolower(substr(get_json_property ($r,$i,'roofShape'),0,3))));
							set_json_property( $r, $i, 'shape', check_shape(strtolower(substr(get_json_property ($r,$i,'shape'),0,3))));
																

					   $b = get_json_property($r,$i,'osm_id');
					   
					   if ( $b == '265932518' )
					   	{
					   	
					   		jsonencode( $r);
					   		echo $r;
							exit;					   		
					   	}


					}
			}				
			

				
			return $r;
		
		}
		

		function check_shape( $b )
		{
							$c='';
							switch ($b)
							{
								case "pyr":
									$c='pyramid';
									break;
								case "con":
									$c='cone';
									break;
								case "dom":
									$c='dome';
									break;
								case "sph":
									$c='sphere';
									break;
								case "cyl":
									$c='cylinder';
									break;
								case "fla":
									$c='cylinder';
									break;

							}								
			return $c;
		}
		
		function set_json_property( &$r, $i, $key, $value )
		{
			if ( $value != NULL && $value != '' )
			{
				$r['features'][$i]['properties'][$key] = $value;
			}
			
		}

		function get_json_property( &$r, $i, $key )
		{
			$value = NULL;
			
			
			
			   if ( isset($r['features'][$i]['properties'][$key]))
			   {
			   		$value=trim($r['features'][$i]['properties'][$key]);
			   		unset( $r['features'][$i]['properties'][$key]);
			   }
		
		    if ( $value == '' ) {$value = NULL; }
		    
			return $value;
		}
?>













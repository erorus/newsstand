import java.io.*;
import java.net.*;
import java.util.*;
import java.text.*;
import java.math.*;
import java.util.zip.*;
import java.security.*;
import java.util.regex.*;

public class WoWIUploader {
	String DESC_LIMIT = "30";
	String SVN_CMD_PATH = "svn";
	String GIT_CMD_PATH = "git";

	// User info
	String username = "";
	String password = "";
	String sessionid = "";

	// File info
	String boundary = "-----" + System.currentTimeMillis();
	String fileID;

	Hashtable<String,Hashtable<String,String>> mods = new Hashtable<String,Hashtable<String,String>>();
	Hashtable<String, String> modData = new Hashtable<String, String>();
	Hashtable<String, String> cmdArgs = new Hashtable<String, String>();
	Hashtable<String, String> impliedArgs = new Hashtable<String, String>();
	Vector<Pattern> ignoreFiles = new Vector<Pattern>();

	String UA = "WoWInterface Mod Uploader $Revision: 52 $";

	URL url;
	File descFile;
	MessageDigest md5;
	HttpURLConnection conn;

	public WoWIUploader(String[] args) throws IOException {
		// Parse out all the arguments
		boolean skipNext = false;
		for( int i=0; i < args.length; i++ ) {
			if( skipNext ) {
				skipNext = false;
				continue;
			}

			String next = "";
			boolean nextValid = false;
			if( i < args.length-1 ) {
				next = args[i+1];
			}

			if( !checkCommand(args[i]) ) {
				System.out.println("Invalid argument used \"" + args[i] + "\"");
				printHelp();
				return;
			}

			if( !next.equals("") && !next.startsWith("-") ) {
				if( !args[i].equals("-ignore") ) {
					cmdArgs.put(args[i].substring(1), next);
				} else {
					ignoreFiles.add(Pattern.compile(next));
				}

				skipNext = true;
			} else if( next.equals("") || next.startsWith("-") ) {
				cmdArgs.put(args[i].substring(1), "true");
				skipNext = false;
			}
		}

		// Show help?
		if( cmdArgs.containsKey("h") || cmdArgs.containsKey("help") ) {
			printHelp();
			return;
		}

		// Imply we're using the current working directory if none is provided
		if( !cmdArgs.containsKey("path") ) {
			cmdArgs.put("path", ".");
			impliedArgs.put("path", "true");
		}

		// Imply svn if you have a path
		if( !cmdArgs.containsKey("svn") && cmdArgs.containsKey("svn-path") ) {
			cmdArgs.put("svn", "true");
			impliedArgs.put("svn", "true");
		}

		// Imply svn is on the current working directory
		if( ( cmdArgs.containsKey("svn") || cmdArgs.containsKey("svn-export") ) && !cmdArgs.containsKey("svn-path") ) {
			cmdArgs.put("svn-path", ".");
			impliedArgs.put("svn-path", "true");
		}

		// Imply git if you have a path
		if( ( cmdArgs.containsKey("git") || cmdArgs.containsKey("git-export") ) && !cmdArgs.containsKey("git-path") ) {
			cmdArgs.put("git", "true");
			impliedArgs.put("git", "true");
		}
		
		// Imply svn is on the current working directory
		if( !cmdArgs.containsKey("git-path") ) {
			cmdArgs.put("git-path", "master");
			impliedArgs.put("git-path", "true");
		}


		// Just list all of our mods
		if( cmdArgs.containsKey("list") ) {
			listMods();
			return;
		}

		// Verify the description file given exists
		if( cmdArgs.containsKey("desc") ) {
			descFile = new File(cmdArgs.get("desc"));
			if( !descFile.exists() ) {
				System.out.println("Cannot find the description file, you gave " + cmdArgs.get("desc"));
				return;
			}
		}

		if( impliedArgs.containsKey("svn") && !cmdArgs.get("svn").equals("true") ) {
			SVN_CMD_PATH = cmdArgs.get("svn");
		}

		if( cmdArgs.containsKey("git") && !cmdArgs.get("git").equals("true") ) {
			GIT_CMD_PATH = cmdArgs.get("git");
		}

		// Probably need input now
		BufferedReader br = new BufferedReader(new InputStreamReader(System.in));

		// Verify log in
		String username = cmdArgs.get("u");
		String password = cmdArgs.get("p");

		for( int i=1; i <= 3; i++ ) {
			if( username == null ) {
				System.out.print("Username: ");
				username = br.readLine();
			} else {
				System.out.println("Username: " + username);
			}

			if( password == null ) {
				System.out.print("Password: ");
				password = br.readLine();
			} else {
				System.out.println("Password: ********");
			}

			login(username, password);

			// Logged in!
			if( !sessionid.equals("") ) {
				System.out.println();
				break;
			} else if( i == 3 ) {
				System.out.println("Too many attempts to login.");
				return;
			} else {
				username = null;
				password = null;
				System.out.println();
			}
		}


		System.out.println("Loading your submitted mods...");

		getUserMods();

		String fid = cmdArgs.get("fid");
		String ftitle = cmdArgs.get("ftitle");

		// Scan the TOC file for the file title
		if( cmdArgs.containsKey("ftoc") ) {
			ftitle = null;

			File[] files = (new File(cmdArgs.get("path"))).listFiles();
			boolean found = false;

			for( File file : files ) {
				if( file.getName().endsWith(".toc") ) {
					found = true;
					System.out.print("Scanning " + file.getName() + "...");

					BufferedReader fr = new BufferedReader(new FileReader(file));
					String line;

					while( (line = fr.readLine()) != null ) {
						if( line.startsWith("## Title:") ) {
							ftitle = line.substring(9).trim();
							System.out.println("title found.");
							break;
						}
					}

					if( ftitle == null ) {
						System.out.println("no title found.");
					}

					fr.close();

					System.out.println();
				}
			}

			if( !found ) {
				System.out.println("Cannot find a valid toc file.");
				System.out.println();
			}
		}

		// Not the cleanest way of doing it, will clean it up later.
		for( int i=1; i <= 10; i++ ) {
			if( i == 10 ) {
				System.out.println("Too many attempts at the file information.");
				return;
			}

			// Grab title or id if need be
			if( ftitle == null ) {
				System.out.print("File Title: ");
				ftitle = br.readLine();
			} else if( ftitle == null && fid == null ) {
				System.out.print("File ID: ");
				fid = br.readLine();
			}

			// Check if we have a match
			boolean matchFound = false;
			Hashtable<String,String> modData = new Hashtable<String,String>();
			for( Enumeration<String> enume = mods.keys(); enume.hasMoreElements(); ) {
				modData = mods.get(enume.nextElement());

				if( ( ftitle != null && ftitle.equalsIgnoreCase(modData.get("title")) ) || ( fid != null && fid.equals(modData.get("id")) ) ) {
					matchFound = true;
					break;
				}
			}

			if( cmdArgs.containsKey("ftitle") || cmdArgs.containsKey("ftoc") || ftitle != null ) {
				if( matchFound ) {
					// Show file id and make sure we want to upload to this
					System.out.println("File Title: " + modData.get("title") + " is #" + modData.get("id"));

					if( !cmdArgs.containsKey("skip") ) {
						System.out.print("Are you sure you want to upload to this mod? [y/n]: ");
						String result = br.readLine();

						if( !result.equalsIgnoreCase("y") && !result.equalsIgnoreCase("yes") ) {
							ftitle = null;
							continue;
						}
					}

					fid = modData.get("id");
					break;

				} else {
					Vector list = new Vector();

					// No match found, check if we have any mods that are within 75%
					// in relevancy and show those titles
					for( Enumeration relEnume = mods.keys(); relEnume.hasMoreElements(); ) {
						Hashtable<String,String> relData = mods.get(relEnume.nextElement());

						float relevancy = checkRelevancy(relData.get("title"), ftitle);
						if( relevancy > 0.75 ) {
							list.add(relData.get("title"));
						}
					}

					if( list.size() == 0 ) {
						System.out.println("No relevant, or exact matches found for \"" + ftitle + "\"");
					} else {
						System.out.println("Found " + list.size() + " close matches to \"" + ftitle + "\"");

						for( int j=0; j < list.size(); j++ ) {
							System.out.println(list.elementAt(j));
						}
					}

					ftitle = null;
				}

			} else if( cmdArgs.containsKey("fid") || fid != null ) {
				if( matchFound ) {
					// Show file title and make sure we want to upload to this
					System.out.println("File ID: " + modData.get("id") + " is " + modData.get("title"));

					if( !cmdArgs.containsKey("skip") ) {
						System.out.print("Are you sure you want to upload to this mod? [y/n]: ");
						String result = br.readLine();

						if( !result.equalsIgnoreCase("y") && !result.equalsIgnoreCase("yes") ) {
							fid = null;
							continue;
						}
					}

					break;

				} else {
					System.out.println("Cannot find the file id #" + fid);
					fid = null;
				}
			}

			System.out.println();
		}

		br.close();

		// Now get the full information
		getFileData(fid);

		// Upload
		System.out.println();
		uploadMod();
	}

	public float checkRelevancy(String text, String checkAgainst) {
		return (float) (checkAgainst.length() - checkAgainst.compareToIgnoreCase(text)) / checkAgainst.length();
	}

	// Retrive list of mods from WoWI
	public void getUserMods() {
		mods.clear();

		try {
			url = new URL("http://www.wowinterface.com/downloads/editfile_xml.php?do=listfiles&l=" + username + "&p=" + MD5Text(password));
			conn = (HttpURLConnection) url.openConnection();
			conn.setRequestProperty("User-Agent", UA);

			String line;

			Hashtable<String,String> modData = new Hashtable<String,String>();

			Pattern field = Pattern.compile("<([a-z]+)>(.+)</([a-z]+)>");
			Matcher match;

			BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
			while( (line = rd.readLine()) != null) {
				// Quick hack
				while( true ) {
					match = field.matcher(line);

					if( match.find() ) {
						String key = match.group(1);

						// Error
						if( key.equals("message") ) {
							System.out.println("Error:" + match.group(2));
							System.out.println();
							break;
						}

						modData.put(key, match.group(2));

						if( key.equals("size") ) {
							mods.put(modData.get("id"), modData);
							modData = new Hashtable<String,String>();
						}

						line = line.replaceAll(key, "");
					} else {
						break;
					}
				}
			}

			rd.close();
			conn.disconnect();

		} catch( MalformedURLException mue ) {
			mue.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}

	// List all of the users mods by id, title, version.
	public void listMods() {
		System.out.println("Retriving mods...");
		getUserMods();

		if( mods.size() == 0 ) {
			System.out.println("You do not appear to have any mods yet, nothing to list.");
			return;
		}

		for( Enumeration<String> enume = mods.keys(); enume.hasMoreElements(); ) {
			Hashtable<String,String> modData = mods.get(enume.nextElement());
			System.out.println( "[" + modData.get("id") + "] " + modData.get("title") + " (" + modData.get("version") + ")");
		}
	}

	// As far as I can see from googling, we can use the built in zip methods
	// safely and pretty much any zip program will be able to extract it
	// Will add a -zip <path> command later if need be and we need the user
	// to provide the zip program

	// Zip a list of files, so we can recurse into it if need be
	private void zipFileList(ZipOutputStream zos, File[] files, String path) {
		// Nothing to zip, return quickly
		if( files.length == 0 ) {
			return;
		}

		try {
			byte[] buf = new byte[1024];
			for( int i=0; i < files.length; i++ ) {
				File file = files[i];
				boolean skipRow = false;

				// Always skip files starting with a period
				if( file.getName().startsWith(".") ) continue;
				if( file.getName().equals("update.bat") ) continue;

				// Don't zip the description file (if any)
				if( descFile != null && file.getAbsolutePath().equals(descFile.getCanonicalPath()) ) continue;


				for( Enumeration<Pattern> enume = ignoreFiles.elements(); enume.hasMoreElements(); ) {
					if( enume.nextElement().matcher(file.getName()).find() ) {
						skipRow = true;
						break;
					}
				}

				// Found a match, next!
				if( skipRow ) continue;

				// Recurse and zip the directory + files in the directory
				if( file.isDirectory() ) {
					String dirPath = path + file.getName() + "/";
					System.out.println("Zipping " + dirPath);

					//zos.putNextEntry(new ZipEntry(dirPath));

					zipFileList(zos, file.listFiles(), dirPath);
					continue;
				}

				// Filter out files depending if we're zipping WoWIU or not
				// Add to the zip
				System.out.println("Zipping " + path +  file.getName());

				zos.putNextEntry(new ZipEntry(path + file.getName()));

				// Now add the contents of the file
				FileInputStream fis = new FileInputStream(file);
				int wrote = 0;
				while( (wrote = fis.read(buf)) > 0) {
					zos.write(buf, 0, wrote);
				}

				// Finish up
				fis.close();
				zos.closeEntry();
				zos.flush();
			}

		} catch( FileNotFoundException fnfe ) {
			fnfe.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}

	// Base folder to start zipping
	public File zipFolder(String zipName, File folder) {
		File[] files = folder.listFiles();
		File zipFile = new File(zipName);

		try {
			// Make sure we have something to zip of course
			if( files == null || files.length == 0 ) {
				System.out.println("No files found in " + folder.getCanonicalPath());
				return null;
			}

			ZipOutputStream zos = new ZipOutputStream(new FileOutputStream(zipFile));
			zipFileList(zos, files, folder.getName() + "/");
			zos.close();

			return zipFile;

		} catch( FileNotFoundException fnfe ) {
			fnfe.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}

		return null;
	}

	// Executes a shell command
	// Only should be used for "generic" commands that
	// wont change between OS's
	public String executeCommand(String[] list) {
		return executeCommand(list, null, false);
	}

	public String executeCommand(String[] list, boolean output) {
		return executeCommand(list, null, output);
	}
	
	public String executeCommand(String[] list, File cwd, boolean output) {
		try {
			ProcessBuilder pb = new ProcessBuilder(list);
			pb.redirectErrorStream();
			
			if( cwd != null ) {
				pb.directory(cwd);
			}
				
			Process proc = pb.start();

			// Check results
			String line;
			String data = "";

			BufferedReader rd = new BufferedReader(new InputStreamReader(proc.getInputStream()));
			while( (line = rd.readLine()) != null) {
				data = data + line + "\r\n";

				if( output )  {
					System.out.println(line);
				}
			}
			rd.close();

			// Anything above 0 is an error in any OS
			// No error, return the data since it was successful
			if( proc.waitFor() == 0 ) {
				return data;

			// Something happened, print the command + error message
			} else {
				String cmd = "";
				for( int i=0; i < list.length; i++ ) {
					if( i > 0 ) {
						cmd = cmd + " ";
					}

					cmd = cmd + list[i];
				}

				System.out.println("Error when trying to run \"" + cmd + "\"");
				return null;
			}

		} catch( InterruptedException ie ) {
			ie.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}

		return null;
	}

	// Request change log from the server
	public String getChangeLog(String limit, String path) {
		if( cmdArgs.containsKey("svn-path") ) {
			if( !limit.equals("") ) {
				return executeCommand(new String[]{SVN_CMD_PATH, "log", cmdArgs.get("svn-path"), "--limit", limit});
			} else {
				return executeCommand(new String[]{SVN_CMD_PATH, "log", cmdArgs.get("svn-path")});
			}
		} else if( cmdArgs.containsKey("git-path") ) {
			if( !limit.equals("") ) {
				return executeCommand(new String[]{GIT_CMD_PATH, "log", cmdArgs.get("git-path"), "-n", limit}, new File(path), false);
			} else {
				return executeCommand(new String[]{GIT_CMD_PATH, "log", cmdArgs.get("git-path")}, new File(path), false);
			}
		}

		return null;
	}

	// Because we can only delete empty folders, we have to recurse through it first
	// this is used for svn-export mainly
	public void deleteContents(File file) {
		File[] files = file.listFiles();
		for( int i=0; i < files.length; i++) {
			if( files[i].isDirectory() ) {
				deleteContents(files[i]);
			} else {
				files[i].delete();
			}
		}

		file.delete();
	}

	// Upload the actual mod
	public void uploadMod() {
		try {
			File uploadFile;
			String exportPath = "";
			String overrideVersion = "";
			if( cmdArgs.containsKey("curse-page") ) {
				url = new URL("http://www.wowace.com/addons/" + cmdArgs.get("curse-page") + "/?api-key=" + cmdArgs.get("api-key"));
				conn = (HttpURLConnection) url.openConnection();
				conn.setRequestProperty("User-Agent", UA);
				conn.setDoOutput(false);
				conn.setUseCaches(false);
	
				String line;
				String response = "";
	
				// Grab response
				BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
				while( (line = br.readLine()) != null) {
					line = line.trim();
					response = response + line + "\r\n";
				}
	
				br.close();
				conn.disconnect();

				Pattern fileMatch = Pattern.compile("/addons/" + cmdArgs.get("curse-page") + "/files/(.+)/\">Download</a>");
				Matcher match = fileMatch.matcher(response);
				
				if( !match.find() ) {
					System.out.println("Failed to find latest download on wowace.");
					System.exit(0);	
				}
				
				url = new URL("http://www.wowace.com/addons/" + cmdArgs.get("curse-page") + "/files/" + match.group(1) + "/");
				conn = (HttpURLConnection) url.openConnection();
				conn.setRequestProperty("User-Agent", UA);
				conn.setDoOutput(false);
				conn.setUseCaches(false);
	
				response = "";
	
				// Grab response
				br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
				while( (line = br.readLine()) != null) {
					line = line.trim();
					
					response = response + line + "\r\n";
				}
	
				br.close();
				conn.disconnect();
				
				Pattern versionMatch = Pattern.compile("<p>tag (.+)<br>");
				match = versionMatch.matcher(response);
				
				if( match.find() ) {
					overrideVersion = match.group(1);	
				}
		
				Pattern downloadMatch = Pattern.compile("http://static.wowace.com/content/files/(.+).zip");
				match = downloadMatch.matcher(response);
				
				if( !match.find() ) {
					System.out.println("Failed to find download URL on wowace.");
					System.exit(0);	
				}
				
				String downloadURL = "http://static.wowace.com/content/files/" + match.group(1) + ".zip";
				
				url = new URL(downloadURL);
				conn = (HttpURLConnection) url.openConnection();
				conn.setRequestProperty("User-Agent", UA);
				conn.setDoOutput(false);
				conn.setUseCaches(false);
				
				fileMatch = Pattern.compile("http://static.wowace.com/content/files/[0-9]+/[0-9]+/(.+).zip");
				match = fileMatch.matcher(downloadURL);

				if( !match.find() ) {
					System.out.println("Failed to find file name :(");
					System.exit(0);	
				}
				
				File file = new File("./" + match.group(1) + ".zip");
				BufferedInputStream in = new BufferedInputStream(conn.getInputStream());
				OutputStream out = new BufferedOutputStream(new FileOutputStream(file));

				byte[] buf = new byte[256];
				int n = 0;
				while ((n=in.read(buf))>=0) {
					out.write(buf, 0, n);
				}
				
				out.flush();
				out.close();
				
				cmdArgs.put("zip", file.getPath());
				file.deleteOnExit();
				
			} else if( !cmdArgs.containsKey("zip") ) {
				// Using SVN, grab the files and then proceed.
				if( cmdArgs.containsKey("svn-export") ) {
					System.out.println("Downloading folder from svn, this may take a few minutes.");

					String path = cmdArgs.get("svn-export");
					if( path.endsWith("/") ) path = path.substring(0, path.length() - 1);
					path = "./" + path.substring(path.lastIndexOf("/") + 1) + "/";
					File oldFile = new File(path);
					if( oldFile.exists() ) {
						deleteContents(oldFile);
					}

					cmdArgs.put("path", path);

					if( !cmdArgs.containsKey("svn-path") || impliedArgs.containsKey("svn-path") ) {
						cmdArgs.put("svn-path", cmdArgs.get("svn-export"));
					}

					if( !cmdArgs.containsKey("svn") ) {
						cmdArgs.put("svn", "true");
					}

					String results = executeCommand(new String[]{"\"" + SVN_CMD_PATH + "\"", "export", cmdArgs.get("svn-export")}, true);
					if( results == null ) {
						System.out.println("Failed to export " + cmdArgs.get("svn-export"));
						return;
					}

					System.out.println();

					exportPath = path;

				// Using git, grab the files and then proceed.
				} else if( cmdArgs.containsKey("git-export") ) {
					System.out.println("Downloading folder from git, this may take a few minutes.");
				
					String path = cmdArgs.get("git-export");
					path  = path.substring(path.lastIndexOf("/") + 1, path.length() - 4);
					if( cmdArgs.containsKey("git-folder") ) {
						path = cmdArgs.get("git-folder");
					}
					
					cmdArgs.put("path", path);

					if( !cmdArgs.containsKey("git-path") || impliedArgs.containsKey("git-path") ) {
						cmdArgs.put("git-path", cmdArgs.get("git-export"));
					}

					if( !cmdArgs.containsKey("git") ) {
						cmdArgs.put("git", "true");
					}
					
					String results;
					if( cmdArgs.containsKey("git-folder") ) {
						results = executeCommand(new String[]{GIT_CMD_PATH, "clone", cmdArgs.get("git-export"), cmdArgs.get("git-folder")}, true);
					} else {
						results = executeCommand(new String[]{GIT_CMD_PATH, "clone", cmdArgs.get("git-export")}, true);
					}
					if( results == null ) {
						System.out.println("Failed to export " + cmdArgs.get("git-export"));
						return;
					}

					System.out.println();

					exportPath = path;
				}
			}
			
			// Prepare the mod for uploading
			String descLog;
			String changeLog;

			String description = modData.get("description");
			// Grab the new description instead of using the mods current one
			if( cmdArgs.containsKey("desc") ) {
				BufferedReader br = new BufferedReader(new FileReader(descFile));

				description = "";
				String line;

				while( (line = br.readLine()) != null ) {
					description = description + line + "\r\n";
				}
			}

			// Convert any special characters back into the actual character
			description = description.replace("&amp;", "&");
			description = description.replace("&quot;", "\"");
			description = description.replace("&lt;", "<");
			description = description.replace("&gt;", ">");

			// Grab the description change log
			if( cmdArgs.containsKey("repo-log") ) {
				System.out.println("Downloading folder from git, this may take a few minutes.");
			
				String path = cmdArgs.get("repo-log");
				path = path.substring(path.lastIndexOf("/") + 1, path.length() - 4);
				if( cmdArgs.containsKey("curse-page") ) {
					path = cmdArgs.get("curse-page");
				}
				
				cmdArgs.put("git-path", "origin");
				String results = executeCommand(new String[]{GIT_CMD_PATH, "clone", cmdArgs.get("repo-log"), cmdArgs.get("curse-page")}, true);
				if( results == null ) {
					System.out.println("Failed to export " + cmdArgs.get("repo-log"));
					return;
				}

				System.out.println();

				exportPath = path;
			}
			
			String changelog = "";
			if( cmdArgs.containsKey("desc-log") ) {
				System.out.println("Retriving last " + DESC_LIMIT + " commits for description log, this may take a few minutes.");
				changelog = getChangeLog(DESC_LIMIT, exportPath);


				if( changelog == null ) {
					System.out.println("Cannot get description change log from server.");
					return;
				}

				String[] logLines = changelog.split("\n");
				String lastAuthor = "";
				String lastDate = "";
				String newLog = "";
				boolean localizationUpdate = false;
				boolean hasDate = false;
				
				for( String line : logLines ) {
					line = line.trim();
					
					if( !line.startsWith("commit ") && !line.equals("") ) {
						if( line.startsWith("Author:") ) {
							String author = line.substring(8).trim();
							if( lastAuthor.equals("") || !lastAuthor.equals(author) ) {
								lastAuthor = author;
								newLog = newLog + "\n" + line;
							}
						} else if( line.startsWith("Date:") ) {
							String date = line.substring(6, 18).trim();
							if( lastDate.equals("") || !lastDate.equals(date) ) {
								lastDate = date;
								if( hasDate ) {
									newLog = newLog + "\n";
								}
								
								newLog = newLog + "\n" + line;
								
								localizationUpdate = false;
								hasDate = true;
							}
						} else if( !line.contains("Localization update") || !localizationUpdate ) {
							line = line.replaceFirst("^-", "");
							line = line.trim();
							newLog = newLog + "\n- " + line;
							
							if( line.contains("Localization update") ) {
								localizationUpdate = true;
							}
						}
					}
				}
				
				changelog = newLog;
			}
			
			String version = "";
			if( cmdArgs.containsKey("repo-log") ) {
				if( overrideVersion.equals("") ) {
					DateFormat dateFormat = new SimpleDateFormat("yyyyMMdd");
					version = "r" + dateFormat.format(new Date());
				} else {
					version = overrideVersion;
				}
			}

                if( cmdArgs.containsKey("ver") ) {
                    version = cmdArgs.get("ver");
				}
			
			if( !cmdArgs.containsKey("zip") ) {
				// Figure out the version to use
				if( cmdArgs.containsKey("ver") ) {
					version = cmdArgs.get("ver");
				} else if( cmdArgs.containsKey("svn") ) {
					String results = executeCommand(new String[]{"\"" + SVN_CMD_PATH + "\"", "info", cmdArgs.get("svn-path")});

					// Got results from it (didn't error)
					if( results != null ) {
						Pattern revVersion = Pattern.compile("Last Changed Rev: ([0-9]+)");
						Matcher match = revVersion.matcher(results);

						if( match.find() ) {
							version = "r" + match.group(1);
						}
					}

					if( version.equals("") ) {
						System.out.println("Cannot upload mod, unable to get file version from svn update.");
						return;
					}

				} else if( cmdArgs.containsKey("git") ) {
					DateFormat dateFormat = new SimpleDateFormat("yyyyMMdd");
					version = "r" + dateFormat.format(new Date());

				} else {
					System.out.println("Cannot upload mod, no file version specified you need to pass -ver <version>, or -svn and will get it from svn update.");
					return;
				}


				// Get the full change log for the changelog.txt file included with the addon.");
				if( cmdArgs.containsKey("log") ) {
					System.out.println("Getting full change log, this may take a few minutes.");
					changeLog = getChangeLog("", exportPath);

					if( changeLog != null ) {
						// We only need it for zipping, so remove when we're done
						File logFile = new File(cmdArgs.get("path") + "/changelog.txt");
						logFile.deleteOnExit();

						FileWriter fw = new FileWriter(logFile);
						fw.write(changeLog);
						fw.close();
					} else {
						System.out.println("Cannot get change log from server.");
						return;
					}
				}

				// Create the zip to upload
				// We create a new file using canonical path because it gives us
				// useful getName when using things like "." as a path
				File folder = new File((new File(cmdArgs.get("path"))).getCanonicalPath());

				if( cmdArgs.containsKey("folder") ) {
					File renameFolder = new File(folder.getParent() + "/" + cmdArgs.get("folder"));
					folder.renameTo(renameFolder);
					folder = renameFolder;

					// Switch export path to the new one
					exportPath = renameFolder.getCanonicalPath();
				}

				String zipName = "./" + folder.getName();

				if( cmdArgs.containsKey("zip-ver") ) {
					zipName = zipName + "-" + version + ".zip";
				} else {
					zipName = zipName + ".zip";
				}

				System.out.println();
				System.out.println("Zipping folder " + folder.getAbsolutePath());
				uploadFile = zipFolder(zipName, folder);
				uploadFile.deleteOnExit();
			} else {
				uploadFile = new File(cmdArgs.get("zip"));
				if( !uploadFile.exists() ) {
					System.out.println("Invalid zip passed " + cmdArgs.get("zip") + " does not exist.");
					return;
				}
			}

			System.out.println();

			if( uploadFile == null ) {
				System.out.println("Failed to upload mod, cannot create the zip file.");
				return;
			}
			
			String overlayID = "0";
			String overlayType = "0";
			if( cmdArgs.containsKey("paypal") ) {
				overlayType = "1";
				overlayID = cmdArgs.get("paypal");
			} else if( cmdArgs.containsKey("pledgie") ) {
				overlayType = "2";
				overlayID =cmdArgs.get("pledgie");
			}

			// Basic post form data
			Hashtable<String, String> formData = new Hashtable<String, String>();
			formData.put("op", "editfile");
			formData.put("type", "0");
			formData.put("mode", "0");
			formData.put("wgp", "1");
			formData.put("archiveold", "1");
			formData.put("allowpa", "1");
			formData.put("fileaction", "replace");
			formData.put("sbutton", "Update File");
			formData.put("ftitle", modData.get("title"));
			formData.put("id", fileID);
			formData.put("s", sessionid);
			formData.put("version", version);
			formData.put("message", description);
			formData.put("overlaytype", overlayType);
			formData.put("overlaysid", overlayID);

			if( !changelog.equals("") ) {
				formData.put("changelog", changelog);
			}

			url = new URL("http://www.wowinterface.com/downloads/editfile.php");
			conn = (HttpURLConnection) url.openConnection();
			conn.setRequestProperty("User-Agent", UA);
			conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=\"" + boundary + "\"");
			conn.setDoOutput(true);

			// I hate HTTP RFC
			// RFC requires that the boundary has two dashes prefixed ON TOP OF
			// any dashes defined in the Content-Type boundary, the end of the boundary
			// is defined by another two dashes suffixed.
			boundary = "--" + boundary;

			// Write out everything
			DataOutputStream dos = new DataOutputStream(conn.getOutputStream());

			// Form data
			for( Enumeration<String> enume = formData.keys(); enume.hasMoreElements(); ) {
				String key = enume.nextElement();

				dos.writeBytes(boundary + "\r\n");
				dos.writeBytes("Content-Disposition: form-data; name=\"" + key + "\"\r\n");
				dos.writeBytes("\r\n");
				dos.writeBytes(formData.get(key) + "\r\n");
			}

			// File data
			dos.writeBytes(boundary + "\r\n");
			dos.writeBytes("Content-Disposition: form-data; name=\"replacementfile\"; filename=\"./" + uploadFile.getName() + "\"\r\n");
			dos.writeBytes("Content-Type: " + conn.guessContentTypeFromName(uploadFile.getName()) + "\r\n");
			dos.writeBytes("\r\n");


			// Actual file
			byte[] buf = new byte[1024];
			int read;

			FileInputStream fis = new FileInputStream(uploadFile);

			while( (read = fis.read(buf, 0, buf.length)) >= 0 ) {
				dos.write(buf, 0, read);
			}

			dos.writeBytes("\r\n");
			dos.writeBytes(boundary + "--\r\n");

			// Send off
			dos.flush();
			dos.close();
			fis.close();

			String line;
			String errorMessage = "";
			boolean readMessage = false;

			// Grab response
			BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream()));
			while( (line = br.readLine()) != null) {
				line = line.trim();

				if( line.equals("<!-- message box -->") ) {
					readMessage = true;
					br.readLine();

				} else if( readMessage && line.equals("<!-- / message box -->") ) {
					// Strip the </div> before the message box end and the line break
					errorMessage = errorMessage.substring(0, errorMessage.length() - 10);

					readMessage = false;
				} else if( readMessage ) {
					errorMessage = errorMessage + line + "\r\n";
				}
			}

			br.close();
			conn.disconnect();

			// Upload successful?
			if( errorMessage.equals("") ) {
				System.out.println("Uploaded " + uploadFile.getName() + " " + version + " for " + modData.get("title") + " (#" + fileID + ")");
				System.out.println();
				System.out.println("Your interface or update has been submitted to our database and is now awaiting approval from one of our file administrators. This process could take awhile depending on how many other authors are submitting new or updated interfaces for us to approve.");
				System.out.println();
				System.out.println("Your submission will be deleted IF");
				System.out.println(" - file upload is incomplete.");
				System.out.println(" - missing zip file or file isn't zipped.");
				System.out.println(" - missing gif/jpg.");
				System.out.println(" - uploaded to the wrong category.");
				System.out.println(" - files that were not modified are included in the zip creating waste.");
				System.out.println(" - unknown exe's vbs's and other executable programs are included in the zip.");
				System.out.println();
				System.out.println("We may or may not contact you if we decline your submission. Just being honest.");

			} else {
				System.out.println("Error: "  + errorMessage);
				System.out.println("Unable to upload " + uploadFile.getName() + " for " + modData.get("title") + " (#" + fileID + ")");
			}

			// Can't use .deleteOnExit(); for a directory structor sadly
			if( cmdArgs.containsKey("svn-export") || cmdArgs.containsKey("git-export") || cmdArgs.containsKey("repo-log") ) {
				deleteContents(new File(exportPath));
			}

		} catch( MalformedURLException mue ) {
			mue.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}

	// Get the latest title/version/description data
	public void getFileData(String fileID) {
		try {
			url = new URL("http://www.wowinterface.com/downloads/editfile_xml.php?do=showfile&id=" + fileID + "&l=" + username + "&p=" + MD5Text(password) );
			conn = (HttpURLConnection) url.openConnection();
			conn.setRequestProperty("User-Agent", UA);

			String data = "";
			String line;

			// Grab servers response
			BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
			while( (line = rd.readLine()) != null) {
				data = data + line + "\r\n";
			}

			rd.close();
			conn.disconnect();

			// Any errors?
			Pattern errorMsg = Pattern.compile("<message>(.+)</message>");
			Matcher match = errorMsg.matcher(data);

			if( match.find() ) {
				System.out.println("Error: " + match.group(1));
				return;
			}

			Pattern title = Pattern.compile("<title>(.+)</title>");
			Pattern version = Pattern.compile("<version>(.+)</version>");

			// Title
			match = title.matcher(data);

			if( match.find() ) {
				modData.put("title", match.group(1));
			} else {
				System.out.println("Cannot find mod title for #" + fileID + ".");
				return;
			}

			// Version
			match = version.matcher(data);

			if( match.find() ) {
				modData.put("version", match.group(1));
			} else {
				System.out.println("Cannot find mod version for #" + fileID + ".");
				return;
			}

			// Description
			int startPos = data.indexOf("<description><![CDATA[");
			int endPos = data.indexOf("]]></description>");

			if( startPos > 0 && endPos > 0 ) {
				modData.put("description", data.substring(startPos + 22, endPos));
			} else {
				System.out.println("Cannot find mod description for #" + fileID + ".");
				return;
			}

			this.fileID = fileID;

		} catch( MalformedURLException mue ) {
			mue.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}

	// Grab verify login info and grab the session id
	public void login(String username, String password) {
		try {
			url = new URL("http://www.wowinterface.com/forums/login.php");
			conn = (HttpURLConnection) url.openConnection();
			conn.setRequestProperty("User-Agent", UA);
			conn.setDoOutput(true);

			OutputStreamWriter osw = new OutputStreamWriter(conn.getOutputStream());
			osw.write("vb_login_username=" + username + "&cb_cookieuser_navbar=1&forceredirect=1&vb_login_password=" + password + "&do=login");
			osw.flush();
			osw.close();

			String line;
			String data = "";

			// Grab servers response
			BufferedReader rd = new BufferedReader(new InputStreamReader(conn.getInputStream()));
			while( (line = rd.readLine()) != null) {
				data = data + line + "\r\n";
			}

			rd.close();
			conn.disconnect();

			Pattern loggedIn = Pattern.compile("Thank you for logging in");
			Pattern loginFailed = Pattern.compile("You have entered an invalid username or password");

			// Login worked?
			if( loggedIn.matcher(data).find() ) {
				/*
				PrintWriter pw = new PrintWriter("test.txt");
				pw.print(data);
				pw.flush();
				pw.close();

				if( true == true ) {
					System.exit(0);
				}
				*/


				// Find the session id from the cookie
				// I suppose, we could just send the cookies instead of s=
				// maybe do that later?
				Pattern sessionPattern = Pattern.compile("s=([a-z0-9]+)\"");
				Matcher match = sessionPattern.matcher(data);

				if( match.find() ) {
					this.sessionid = match.group(1);
					this.username = username;
					this.password = password;

					System.out.println("Logged into " + username + ", session id " + sessionid);

				// None found
				} else {
					this.sessionid = "";
					System.out.println("Logged in, but cannot find the session id in the cookie.");
					System.out.println("Cookie: " + conn.getHeaderField("Set-Cookie"));
				}

			} else if( loginFailed.matcher(data).find() ) {
				this.sessionid = "";
				System.out.println("Cannot log in, invalid username or password entered." );
			}
		} catch( MalformedURLException mue ) {
			mue.printStackTrace();
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}

	// editfile_xml.php requires MD5ed passwords, and this is a quick/easy way to access it.
	public String MD5Text(String text) {
		if( md5 == null ) {
			try {
				md5 = MessageDigest.getInstance("MD5");
			} catch( NoSuchAlgorithmException nsae ) {
				nsae.printStackTrace();
			}
		}

		md5.reset();
		try {
			md5.update(text.getBytes("UTF-8"));
		} catch( UnsupportedEncodingException uee ) {
			uee.printStackTrace();
		}

		// Not exactly the most efficent method
		// but we only need it once per a run
		byte digest[] = md5.digest();

		StringBuffer sb = new StringBuffer();
		for(int i=0; i < digest.length; i++) {
			String str = Integer.toHexString(digest[i]);
			if( str.length() < 2 ) {
				sb.append("0" + str);
			} else if( str.length() > 2 ) {
				sb.append(str.substring(str.length() - 2));
			} else {
				sb.append(str);
			}
		}

		return sb.toString();
	}

	// For turning an array into a hashtable with the commands
	public boolean checkCommand(String cmd) {
		if( cmd.equals("-u") || cmd.equals("-p") || cmd.equals("-list") || cmd.equals("-ver") || cmd.equals("-log")
		|| cmd.equals("-desc-log") || cmd.equals("-svn") || cmd.equals("-zip") || cmd.equals("-fid")
		|| cmd.equals("-zip-ver") || cmd.equals("-h") || cmd.equals("-help") || cmd.equals("-path") || cmd.equals("-svn-path") || cmd.equals("-skip") || cmd.equals("-svn-export")
		|| cmd.equals("-ignore") || cmd.equals("-desc") || cmd.equals("-ftitle") || cmd.equals("-ftoc") || cmd.equals("-zip") || cmd.equals("-git") || cmd.equals("-git-path")
		|| cmd.equals("-git-export") || cmd.equals("-folder") || cmd.equals("-paypal") || cmd.equals("-pledgie") || cmd.equals("-git-folder") || cmd.equals("-api-key") || cmd.equals("-curse-page") || cmd.equals("-repo-log") ) {
			return true;
		}

		return false;
	}

	// Why print help of course!
	public void printHelp() {
		System.out.println("java WoWIUploader [args]");
		System.out.println("-u <username>	WoWInterface account name");
		System.out.println("-p <password>	WoWInterface account password");
		System.out.println("-ver <version>	File version for uploading");
		System.out.println("-log		Includes a changelog-<version>.txt file with the mod zip.");
		System.out.println("-desc-log	Includes the last " + DESC_LIMIT + " commits in the mod description.");
		System.out.println("-svn <path>	Path to the svn command line, usually you can just use -svn.");
		//System.out.println("-git <path>	Path to the git command line, usually you can just use -git.");
		System.out.println("-fid <fileid>	File ID to update on WoWInterface.");
		System.out.println("-ftitle <title> Attempts to find the file id using the mod title.");
		System.out.println("-ftoc		Attempts to scan the TOC file in the given path for the file title.");
		System.out.println("-folder <name>    Allows you to force a specific folder name, useful for branches.");
		System.out.println("-desc <path>	Replaces the mod description which the file passed.");
		System.out.println("-skip		Skips confirmation on the mod to upload to");
		System.out.println("-zip-ver	Uploads the zip with the file name <folder>-<version>.zip");
		System.out.println("-svn-export <url> Grabs the specified SVN directory URL and uploads it, -path and -svn-path are not needed for this.");
		System.out.println("-svn-path <dir>	Path to the svn to grab the change log/revision number from, only needed if the current working directory isn't the svn repo itself.");
		//System.out.println("-git-export <url> Grabs the specified git directory URL and uploads it, -path and -git-path are not needed for this.");
		//System.out.println("-git-path <dir>	Path to the git to grab the change log/revision number from, only needed if the current working directory isn't the git repo itself.");
		System.out.println("-path <dir>	Path to the mod to be zipped/uploaded, only needed if the current working directory isn't the mod itself.");
		System.out.println("-zip <dir> Zipped file to upload, cannot use -zip-ver or -log with this.");
		System.out.println("-ignore <pattern> Lets you choose which files should not be included in the zip, you add as many -ignore args as needed.");
		System.out.println("-paypal <id> Adds a PayPal donation overlay to the addon");
		System.out.println("-pledgie <id> Adds a Pledgie donation overlay to the addon");
		System.out.println();
		System.out.println();
		System.out.println("-u, -p and -fid are not required but if they aren't provided will ask you to enter them manually.");
		System.out.println("If you do not pass a -ver but you do pass -svn will attempt to find the version number by doing an svn update *, if that fails will exit.");
		System.out.println("Because -log and -desc-log both require svn, it's implied that -svn was included when checking those.");
	}

	public static void main(String[] args) {
		try {
			new WoWIUploader(args);
		} catch( IOException ioe ) {
			ioe.printStackTrace();
		}
	}
}

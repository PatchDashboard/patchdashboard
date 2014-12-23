Option Explicit
Dim nRebootStatusOption
Dim cCriticalSecurityOption, cImportantSecurityOption
Dim cModerateSecurityOption, cLowSecurityOption
Dim cCriticalUpdateOption, cImportantUpdateOption
Dim wCriticalSecurityOption, wImportantSecurityOption
Dim wModerateSecurityOption, wLowSecurityOption
Dim wCriticalUpdateOption, wImportantUpdateOption
CONST rOK = 0
CONST rWarning = 1
CONST rCritical = 2
CONST rUnknown = 3

nRebootStatusOption = rWarning
cCriticalSecurityOption = True
cImportantSecurityOption = False
cModerateSecurityOption = False
cLowSecurityOption = False
cCriticalUpdateOption = True
cImportantUpdateOption = False
wCriticalSecurityOption = False
wImportantSecurityOption = True
wModerateSecurityOption = True
wLowSecurityOption = True
wCriticalUpdateOption = False
wImportantUpdateOption = True

'====================== vars ========================
Dim objUpdateSession, objUpdateSearcher, colSearchResult, objUpdate, i, y
Dim strCriticalSecurity, strImportantSecurity, strModerateSecurity, strLowSecurity
Dim blnCriticalSecurity, blnImportantSecurity, blnModerateSecurity, blnLowSecurity

Dim strCriticalUpdate, strImportantUpdate
Dim blnCriticalUpdate, blnImportantUpdate
Dim strReturnSummary, strReturnDetails, strReturnText

Dim strScriptVersion
Dim nCriticalSecurity, nImportantSecurity, nModerateSecurity, nLowSecurity, nCriticalUpdate, nImportantUpdate
Dim bInvalidArgument, bDisplayHelp

Set objUpdateSession = CreateObject("Microsoft.Update.Session")
Set objUpdateSearcher = objUpdateSession.CreateUpdateSearcher()


blnCriticalSecurity = False
blnImportantSecurity = False

Dim objFSO, ObjFile
Set objFSO = CreateObject("Scripting.FileSystemObject")
Set objFile = objFSO.CreateTextFile("C:\WindowsUpdates.log", True)

' Get Options from user
GetOptions

If (bDisplayHelp) Then
	DisplayHelp
ElseIf (bInvalidArgument) Then
	DisplayInvalidArgument
Else
	CheckUpdates
End If

' ======================== subs/functions ========================
Sub CheckUpdates
	Dim colCategory, blnIsCritical
	Set colSearchResult = objUpdateSearcher.Search("IsInstalled=0 and Type='Software'")

	For i = 0 To colSearchResult.Updates.Count-1
		Set objUpdate = colSearchResult.Updates.Item(i)
		If (objUpdate.MsrcSeverity = "Critical") Then
			blnCriticalSecurity = True
			strCriticalSecurity = strCriticalSecurity & vbCrLF & objUpdate.Title
		Elseif (objUpdate.MsrcSeverity = "Important") Then
			blnImportantSecurity = True
			strImportantSecurity = strImportantSecurity & vbCrLF & objUpdate.Title
		Elseif (objUpdate.MsrcSeverity = "Moderate") Then
			blnModerateSecurity = True
			strModerateSecurity = strModerateSecurity & vbCrLF & objUpdate.Title
		Elseif (objUpdate.MsrcSeverity = "Low") Then
			blnLowSecurity = True
			strLowSecurity = strLowSecurity & vbCrLF & objUpdate.Title
		Elseif (objUpdate.AutoSelectOnWebSites = True) Then
			blnIsCritical = False
			For Each colCategory in objUpdate.Categories
				If (colCategory.Name = "Critical Updates") Then
					blnIsCritical = True
				End If
			Next
			If (blnIsCritical) Then
				blnCriticalUpdate = True
				strCriticalUpdate = strCriticalUpdate & vbCrLF & objUpdate.Title
			Else
				blnImportantUpdate = True
				strImportantUpdate = strImportantUpdate & vbCrLF & objUpdate.Title
			End If
		End If
	Next

	If (blnCriticalSecurity) Then
		strReturnSummary = nCriticalSecurity & " Critical Security"
		strReturnDetails = strCriticalSecurity
	End If
	If (blnImportantSecurity) Then
		If (Len(strReturnSummary) = 0) Then
			strReturnSummary = nImportantSecurity & " Important Security"
		Else
			strReturnSummary = strReturnSummary & ", " & nImportantSecurity & " Important Security"
		End If
		If (Len(strReturnDetails) = 0) Then
			strReturnDetails = strImportantSecurity
		Else
			strReturnDetails = strReturnDetails & strImportantSecurity
		End If		
	End If
	If (blnModerateSecurity) Then
		If (Len(strReturnSummary) = 0) Then
			strReturnSummary = nModerateSecurity & " Moderate Security"
		Else
			strReturnSummary = strReturnSummary & ", " & nModerateSecurity & " Moderate Security"
		End If
		If (Len(strReturnDetails) = 0) Then
			strReturnDetails = strModerateSecurity
		Else
			strReturnDetails = strReturnDetails & strModerateSecurity
		End If		
	End If
	If (blnLowSecurity) Then
		If (Len(strReturnSummary) = 0) Then
			strReturnSummary = nLowSecurity & " Low Security"
		Else
			strReturnSummary = strReturnSummary & ", " & nLowSecurity & " Low Security"
		End If
		If (Len(strReturnDetails) = 0) Then
			strReturnDetails = strLowSecurity
		Else
			strReturnDetails = strReturnDetails & strLowSecurity
		End If		
	End If

	If (blnCriticalUpdate) Then
		If (Len(strReturnSummary) = 0) Then
			strReturnSummary = nCriticalUpdate & " Critical Updates"
		Else
			strReturnSummary = strReturnSummary & ", " & nCriticalUpdate & " Critical Updates"
		End If
		If (Len(strReturnDetails) = 0) Then
			strReturnDetails = strCriticalUpdate
		Else
			strReturnDetails = strReturnDetails & strCriticalUpdate
		End If		
	End If
	
	If (blnImportantUpdate) Then
		If (Len(strReturnSummary) = 0) Then
			strReturnSummary = nImportantUpdate & " Important Updates"
		Else
			strReturnSummary = strReturnSummary & ", " & nImportantUpdate & " Important Updates"
		End If
		If (Len(strReturnDetails) = 0) Then
			strReturnDetails = strImportantUpdate
		Else
			strReturnDetails = strReturnDetails & strImportantUpdate
		End If		
	End If
	
	strReturnText = ""
	If (Len(strReturnSummary) > 0) Then
		strReturnText = strReturnDetails
	End If

	
	If (blnCriticalSecurity = True And cCriticalSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnImportantSecurity = True And cImportantSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnModerateSecurity = True And cModerateSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnLowSecurity = True And cLowSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnCriticalUpdate = True And cCriticalUpdateOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnImportantUpdate = True And cImportantUpdateOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rCritical)
	Elseif (blnCriticalSecurity = True And wCriticalSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)
	Elseif (blnImportantSecurity = True And wImportantSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)
	Elseif (blnModerateSecurity = True And wModerateSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)
	Elseif (blnLowSecurity = True And wLowSecurityOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)
	Elseif (blnCriticalUpdate = True And wCriticalUpdateOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)
	Elseif (blnImportantUpdate = True And wImportantUpdateOption = True) Then
		Wscript.Echo strReturnText
		objFile.WriteLine strReturnText
		Wscript.Quit(rWarning)	
	Else
		Dim objUpdateSystemInfo, blnRebootRequired
		Set objUpdateSystemInfo = CreateObject("Microsoft.Update.SystemInfo")
		blnRebootRequired = objUpdateSystemInfo.RebootRequired
		
		If (blnRebootRequired = True And nRebootStatusOption > 0) Then
			Wscript.Echo "A reboot is required"
			objFile.WriteLine "A reboot is required"
			Wscript.Quit(nRebootStatusOption)
		End If
		If (Len(strReturnText) > 0) Then
			Wscript.Echo strReturnText
			objFile.WriteLine strReturnText
		Else
			Wscript.Echo "OK: No Important or Critical patches missing (" & strScriptVersion & ")"
			objFile.WriteLine "OK: No Important or Critical patches missing (" & strScriptVersion & ")"
		End If
		Wscript.Quit(rOK)	
	End If
	
End Sub 

Sub DisplayHelp
	WScript.Echo "Check Available Updates v." 
	Wscript.Echo "----------------------------------------------"
	WScript.Echo "Usage: cscript.exe check_updates.vbs [options]"
 	WScript.Echo VbCrLf
 	WScript.Echo " -h	- Display help"
	Wscript.Quit(rUnknown)
End Sub


Sub GetOptions()
	Dim objArgs, nArgs

	Set objArgs = WScript.Arguments
	If (objArgs.Count > 0) Then
		For nArgs = 0 To objArgs.Count - 1
			SetOptions objArgs(nArgs)
		Next
	End If
End Sub

Sub DisplayInvalidArgument()
	Wscript.Echo "Invalid arguments, check help with cscript.exe check_updates.vbs -h"
	Wscript.Quit(rUnknown)	
End Sub

Sub SetOptions(strOption)
	Dim strFlag, strParameter
	Dim nArguments
	nArguments = Len(strOption)
	If (nArguments < 2) Then
		bInvalidArgument = True
	Else
		strFlag = Left(strOption,2)
		Select Case strFlag
			Case "-c"
				cCriticalSecurityOption = False
				cImportantSecurityOption = False
				cModerateSecurityOption = False
				cLowSecurityOption = False
				cCriticalUpdateOption = False
				cImportantUpdateOption = False
				If (nArguments > 2) Then
					For i = 3 To nArguments
						strParameter = Mid(strOption,i,1)
						Select Case strParameter
							Case "c"
								cCriticalSecurityOption = True
							Case "i"
								cImportantSecurityOption = True
							Case "m"
								cModerateSecurityOption = True
							Case "l"
								cLowSecurityOption = True
							Case "U"
								cCriticalUpdateOption = True
							Case "u"
								cImportantUpdateOption = True
							Case Else
								bInvalidArgument = True
						End Select
					Next
				End If
			Case "-w"
				wCriticalSecurityOption = False
				wImportantSecurityOption = False
				wModerateSecurityOption = False
				wLowSecurityOption = False
				wCriticalUpdateOption = False
				wImportantUpdateOption = False
				If (nArguments > 2) Then
					For i = 3 To nArguments
						strParameter = Mid(strOption,i,1)
						Select Case strParameter
							Case "c"
								wCriticalSecurityOption = True
							Case "i"
								wImportantSecurityOption = True
							Case "m"
								wModerateSecurityOption = True
							Case "l"
								wLowSecurityOption = True
							Case "U"
								wCriticalUpdateOption = True
							Case "u"
								wImportantUpdateOption = True
							Case Else
								bInvalidArgument = True
						End Select
					Next
				End If
			Case "-r"
				nRebootStatusOption = rOK
				If (nArguments > 2) Then
					For i = 3 To nArguments
						strParameter = Mid(strOption,i,1)
						Select Case strParameter
							Case "o"
								nRebootStatusOption = rOK
							Case "w"
								nRebootStatusOption = rWarning
							Case "c"
								nRebootStatusOption = rCritical
							Case Else
								bInvalidArgument = True
						End Select
					Next
				End If
			
			Case "-h"
				bDisplayHelp = True
			Case Else
				bInvalidArgument = True
		End Select
	End If
End Sub

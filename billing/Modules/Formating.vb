Module Formating
    Public Sub NumberFormat(ByVal Text As System.Windows.Forms.TextBox)


        If Len(Text.Text) > 0 Then

            Text.Text = FormatNumber(CDbl(Text.Text), TriState.True)

            Dim x As Integer = Text.SelectionStart.ToString

            If x = 0 Then

                Text.SelectionStart = Len(Text.Text)

                Text.SelectionLength = 0

            Else
                Text.SelectionStart = x

                Text.SelectionLength = 0

            End If

        End If
    End Sub


    Function myDecimal(Percentage As String) As Double

        myDecimal = Percentage / 100    'makes the percentage string a number using Val(), divides that by'

    End Function


    Public Function SpellNumber(ByVal MyNumber As String) As String
        Dim Dollars As String = ""
        Dim Cents As String = ""
        Dim Temp As String = ""
        Dim DecimalPlace, Count As Integer
        Dim Place(9) As String
        Place(2) = " Thousand "
        Place(3) = " Million "
        Place(4) = " Billion "
        Place(5) = " Trillion "
        ' String representation of amount.
        MyNumber = Trim(Str(MyNumber))
        ' Position of decimal place 0 if none.
        DecimalPlace = InStr(MyNumber, ".")
        ' Convert cents and set MyNumber to dollar amount.
        If DecimalPlace > 0 Then
            Cents = GetTens(Left(Mid(MyNumber, DecimalPlace + 1) & _
            "00", 2))
            MyNumber = Trim(Left(MyNumber, DecimalPlace - 1))
        End If
        Count = 1
        Do While MyNumber <> ""
            Temp = GetHundreds(Right(MyNumber, 3))
            If Temp <> "" Then Dollars = Temp & Place(Count) & Dollars
            If Len(MyNumber) > 3 Then
                MyNumber = Left(MyNumber, Len(MyNumber) - 3)
            Else
                MyNumber = ""
            End If
            Count = Count + 1
        Loop
        Select Case Dollars
            Case ""
                Dollars = "No Pesos"
            Case "One"
                Dollars = "One Pesos Only"
            Case Else
                Dollars = Dollars & " Pesos Only"
        End Select
        Select Case Cents
            Case ""
                Cents = ""
            Case "One"
                Cents = " and One Cent Only"
            Case Else
                Cents = " and " & Cents & " Cents"
        End Select
        SpellNumber = Dollars & Cents
    End Function
    ' Converts a number from 100-999 into text 
    Function GetHundreds(ByVal MyNumber As String) As String
        Dim Result As String
        If Val(MyNumber) = 0 Then : Return "" : Exit Function : End If
        MyNumber = Right("000" & MyNumber, 3)
        ' Convert the hundreds place.
        If Mid(MyNumber, 1, 1) <> "0" Then
            Result = GetDigit(Mid(MyNumber, 1, 1)) & " Hundred "
        End If
        ' Convert the tens and ones place.
        If Mid(MyNumber, 2, 1) <> "0" Then
            Result = Result & GetTens(Mid(MyNumber, 2))
        Else
            Result = Result & GetDigit(Mid(MyNumber, 3))
        End If
        GetHundreds = Result
    End Function
    ' Converts a number from 10 to 99 into text. 
    Function GetTens(ByVal TensText As String) As String
        Dim Result As String
        Result = ""           ' Null out the temporary function value.
        If Val(Left(TensText, 1)) = 1 Then   ' If value between 10-19...
            Select Case Val(TensText)
                Case 10 : Result = "Ten"
                Case 11 : Result = "Eleven"
                Case 12 : Result = "Twelve"
                Case 13 : Result = "Thirteen"
                Case 14 : Result = "Fourteen"
                Case 15 : Result = "Fifteen"
                Case 16 : Result = "Sixteen"
                Case 17 : Result = "Seventeen"
                Case 18 : Result = "Eighteen"
                Case 19 : Result = "Nineteen"
                Case Else
            End Select
        Else                                 ' If value between 20-99...
            Select Case Val(Left(TensText, 1))
                Case 2 : Result = "Twenty "
                Case 3 : Result = "Thirty "
                Case 4 : Result = "Forty "
                Case 5 : Result = "Fifty "
                Case 6 : Result = "Sixty "
                Case 7 : Result = "Seventy "
                Case 8 : Result = "Eighty "
                Case 9 : Result = "Ninety "
                Case Else
            End Select
            Result = Result & GetDigit _
            (Right(TensText, 1))  ' Retrieve ones place.
        End If
        GetTens = Result
    End Function
    ' Converts a number from 1 to 9 into text. 
    Function GetDigit(ByVal Digit As String) As String
        Select Case Val(Digit)
            Case 1 : GetDigit = "One"
            Case 2 : GetDigit = "Two"
            Case 3 : GetDigit = "Three"
            Case 4 : GetDigit = "Four"
            Case 5 : GetDigit = "Five"
            Case 6 : GetDigit = "Six"
            Case 7 : GetDigit = "Seven"
            Case 8 : GetDigit = "Eight"
            Case 9 : GetDigit = "Nine"
            Case Else : GetDigit = ""
        End Select
    End Function
End Module

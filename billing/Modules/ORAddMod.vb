Imports System.Data
Imports System.Data.SqlClient
Module ORAddMod
    Public Sub AddOR()
        
        Dim mydate As Date = frmor.txtTime.Text
        Dim month As String = mydate.ToString("MM")
        Dim day As String = mydate.ToString("dd")
        Dim year As String = mydate.ToString("yyyy")
        Dim period As String


        If month = "01" And day <= 25 Then
            period = year + "001"
        ElseIf month = "01" And day > 25 Then
            period = year + "002"
        ElseIf month = "02" And day <= 25 Then
            period = year + "002"
        ElseIf month = "02" And day > 25 Then
            period = year + "003"
        ElseIf month = "03" And day <= 25 Then
            period = year + "003"
        ElseIf month = "03" And day > 25 Then
            period = year + "004"
        ElseIf month = "04" And day <= 25 Then
            period = year + "004"
        ElseIf month = "04" And day > 25 Then
            period = year + "005"
        ElseIf month = "05" And day <= 25 Then
            period = year + "005"
        ElseIf month = "05" And day > 25 Then
            period = year + "006"
        ElseIf month = "06" And day <= 25 Then
            period = year + "006"
        ElseIf month = "06" And day > 25 Then
            period = year + "007"
        ElseIf month = "07" And day <= 25 Then
            period = year + "007"
        ElseIf month = "07" And day > 25 Then
            period = year + "008"
        ElseIf month = "08" And day <= 25 Then
            period = year + "008"
        ElseIf month = "08" And day > 25 Then
            period = year + "009"
        ElseIf month = "09" And day <= 25 Then
            period = year + "009"
        ElseIf month = "09" And day > 25 Then
            period = year + "010"
        ElseIf month = "10" And day <= 25 Then
            period = year + "010"
        ElseIf month = "10" And day > 25 Then
            period = year + "011"
        ElseIf month = "11" And day <= 25 Then
            period = year + "011"
        ElseIf month = "11" And day > 25 Then
            period = year + "012"
        ElseIf month = "12" And day <= 31 Then
            period = year + "012"
        End If

     
        Try
            myCon.open()
            With frmor

                myCmd = New SqlCommand("INSERT INTO tbl_or_trans (or_acc_num,or_accname,or_remarks,or_date,or_status,or_employee,or_bill_vat,or_bill_amount,or_htax,or_net,or_check_num,or_num,or_cash,or_period,or_bank,or_book,or_indiprint)VALUES ('" & .txtAccountno.Text & "','" & .lblAccname.Text & "','" & .txtRemarks.Text & "','" & mydate & "','FALSE','" & frmBilling.txtnametemp.Text & "','" & Convert.ToDecimal(.txtORVat.Text) & "','" & Convert.ToDecimal(.txtORBillAmount.Text) & "','" & Convert.ToDecimal(.txtHTax.Text) & "','" & Convert.ToDecimal(.txtNetIncome.Text) & "','" & frmORCash.txtCheckno.Text & "','" & .txtOrno.Text & "','" & Convert.ToDecimal(frmORCash.txtCash.Text) & "','" & period & "','" & .txtbank.Text & "','OR001','N')", myCon)
                myCmd.ExecuteNonQuery()

                Dim mycmd1 = New SqlCommand("DELETE FROM tbl_or_no WHERE or_num ='" & .txtOrno.Text & "'", myCon)
                mycmd1.ExecuteNonQuery()

            End With

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try

        With frmor

            .txtOrno.Text = ""
            .txtAccountno.Text = ""
            .lblAccname.Text = ""
            .txtRemarks.Text = ""
            '.dgOrItems.DataSource = ""
            .txtORBillAmount.Text = 0
            .txtORVat.Text = 0
            .txtNetIncome.Text = 0
            .txtHTax.Text = 0
            .txtbank.Text = ""
            .txtBstyle.Text = ""
            .lblTin.Text = ""
            .lblAddress.Text = ""
            .txtbank.Enabled = False
            frmORCash.Dispose()
            viewORItems()
        End With

    End Sub

    Public Sub updateOR()

        Dim time As Date = frmor.lbldatetrans.Text
        Dim month As String = time.ToString("MM")
        Dim day As String = time.ToString("dd")
        Dim year As String = time.ToString("yyyy")
        Dim period As String


        If month = "01" And day <= 25 Then
            period = year + "001"
        ElseIf month = "01" And day > 25 Then
            period = year + "002"
        ElseIf month = "02" And day <= 25 Then
            period = year + "002"
        ElseIf month = "02" And day > 25 Then
            period = year + "003"
        ElseIf month = "03" And day <= 25 Then
            period = year + "003"
        ElseIf month = "03" And day > 25 Then
            period = year + "004"
        ElseIf month = "04" And day <= 25 Then
            period = year + "004"
        ElseIf month = "04" And day > 25 Then
            period = year + "005"
        ElseIf month = "05" And day <= 25 Then
            period = year + "005"
        ElseIf month = "05" And day > 25 Then
            period = year + "006"
        ElseIf month = "06" And day <= 25 Then
            period = year + "006"
        ElseIf month = "06" And day > 25 Then
            period = year + "007"
        ElseIf month = "07" And day <= 25 Then
            period = year + "007"
        ElseIf month = "07" And day > 25 Then
            period = year + "008"
        ElseIf month = "08" And day <= 25 Then
            period = year + "008"
        ElseIf month = "08" And day > 25 Then
            period = year + "009"
        ElseIf month = "09" And day <= 25 Then
            period = year + "009"
        ElseIf month = "09" And day > 25 Then
            period = year + "010"
        ElseIf month = "10" And day <= 25 Then
            period = year + "010"
        ElseIf month = "10" And day > 25 Then
            period = year + "011"
        ElseIf month = "11" And day <= 25 Then
            period = year + "011"
        ElseIf month = "11" And day > 25 Then
            period = year + "012"
        ElseIf month = "12" And day <= 31 Then
            period = year + "012"
        End If


        Try
            If frmor.txtperiod.Text <> period Then
                frmor.txtNotice.Text = "Date is out of the original period. Please try again."
            Else
                myCon.open()
                With frmor

                    myCmd = New SqlCommand(String.Format("UPDATE tbl_or_trans SET or_acc_num = '{0}',or_accname = '{1}',or_remarks = '{2}',or_date = '{3}',or_period = '{4}',or_bank = '{5}' WHERE or_num = '{6}'", .txtAccountno.Text, .lblAccname.Text, .txtRemarks.Text, time, period, frmor.txtbank.Text, frmor.txtOrno.Text), myCon)
                    myCmd.ExecuteNonQuery()
                    frmor.txtNotice.Text = "Transaction Updated."
                End With
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try



    End Sub
End Module

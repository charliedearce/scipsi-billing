Imports System.Data
Imports System.Data.SqlClient


Module BillingMod

    Public Sub vBillno()

        'If frmBilling.txtBillno.Text = "" Then
        '    frmBilling.txtBillno.Focus()
        '    frmBilling.txtNotice.Text = "Please Enter Billing number!"

        'Else

        '    Try
        '        myCon.open()

        '        myCmd = New SqlCommand("SELECT * FROM tbl_bill_no WHERE b_num = '" & frmBilling.txtBillno.Text & "'", myCon)
        '        myReader = myCmd.ExecuteReader()

        '        If myReader.HasRows Then
        '            If myReader.Read And myReader("b_status") = "Y" Then

        '                myCon.close()
        '                frmBilling.txtBillno.Focus()
        '                frmBilling.txtNotice.Text = "Bill number is Already in-use for Transaction."
        '            Else

        '                frmBilling.txtTempno.Text = myReader("b_num")
        '                frmBilling.txtNotice.Text = ""
        '                myReader.Dispose()
        '                Dim myCmd1 = New SqlCommand("UPDATE tbl_bill_no SET b_status = 'Y' WHERE b_num = '" & frmBilling.txtBillno.Text & "'", myCon)
        '                myCmd1.ExecuteNonQuery()

        '            End If

        '        Else

        '            frmBilling.txtBillno.Text = ""
        '            frmBilling.txtNotice.Text = "Invalid Billing number! Please Try Again."
        '            frmBilling.txtBillno.Focus()

        '        End If

        '        If frmBilling.txtBillno.Text <> frmBilling.txtTempno.Text Then
        '            myCon.open()
        '            Dim mycmd2 = New SqlCommand("UPDATE tbl_bill_no SET b_status = 'N' WHERE b_num = '" & frmBilling.txtTempno.Text & "'", myCon)
        '            mycmd2.ExecuteNonQuery()
        '            frmBilling.txtTempno.Text = ""
        '            mycmd2.Dispose()
        '            myCon.close()
        '        End If


        '    Catch ex As Exception
        '        frmBilling.txtNotice.Text = "Invalid Billing number! Please Try Again."
        '        MsgBox(ex.Message)
        '    Finally
        '        myCmd.Dispose()
        '        myCon.close()
        '    End Try
        'End If
    End Sub

    Public Sub vAccno()

        If frmBilling.txtAccountno.Text = "     -" Then
            frmBilling.txtAccountno.Focus()
            frmBilling.txtNotice.Text = "Please Enter Account number!"
            frmBilling.lblAccname.Text = ""
            frmBilling.lblAddress.Text = ""
            frmBilling.lblTin.Text = ""
            frmBilling.txtBstyle.Text = ""
        Else


            Try
                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_account WHERE ac_code = '" & frmBilling.txtAccountno.Text & "'", myCon)
                myReader = myCmd.ExecuteReader()

                If myReader.HasRows Then
                    If myReader.Read Then

                        If (myReader("ac_name") = "WALK-IN" And frmBilling.lblAccname.Text = "") Then
                            frmBilling.labelstatus.Text = 1
                            frmWalkin.ShowDialog()
                        Else
                            If myReader("ac_name") = "WALK-IN" Then
                            Else
                                frmBilling.labelstatus.Text = 0
                                frmBilling.lblAccname.Text = myReader("ac_name")
                                frmBilling.lblAddress.Text = myReader("ac_address")
                                frmBilling.lblTin.Text = myReader("ac_tin")
                                frmBilling.txtBstyle.Text = myReader("ac_busi")
                            End If
                        End If

                    End If
                    frmBilling.txtNotice.Text = ""
                Else
                    frmBilling.txtNotice.Text = "Invalid Account number! Please Try Again."
                    frmBilling.txtAccountno.Focus()
                    frmBilling.txtAccountno.Text = ""
                    frmBilling.lblAccname.Text = ""
                    frmBilling.lblAddress.Text = ""
                    frmBilling.lblTin.Text = ""
                    frmBilling.txtBstyle.Text = ""

                End If

            Catch ex As Exception
                frmBilling.txtAccountno.Focus()
                frmBilling.txtAccountno.Text = ""
                frmBilling.lblAccname.Text = ""
                frmBilling.lblAddress.Text = ""
                frmBilling.lblTin.Text = ""
                frmBilling.txtBstyle.Text = ""
                frmBilling.txtNotice.Text = "Invalid Account number! Please Try Again."
            Finally
                myCmd.Dispose()
                myCon.close()
            End Try
        End If

    End Sub


    Public Sub autocomplete()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        Try
            myCon.close()
            myCon.open()
            myCmd = New SqlCommand("SELECT * FROM tbl_vessel", myCon)
            myAdapter = New SqlDataAdapter(myCmd)
            myAdapter.Fill(dt)
            Dim r As DataRow
            frmBilling.txtVessel.AutoCompleteCustomSource.Clear()
            For Each r In dt.Rows
                frmBilling.txtVessel.AutoCompleteCustomSource.Add(r.Item(1).ToString)
            Next

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try

    End Sub

    Public Sub saveBill()
        Dim time As Date = frmBilling.txtTime.Text
        Dim status As String = "FALSE"
        Dim route As String
        Dim type As String

        Dim month As String = time.ToString("MM")
        Dim day As String = time.ToString("dd")
        Dim year As String = time.ToString("yyyy")
        Dim period As String
        Dim reference As String
        Dim ref As String
        Dim indivat As String
        Dim indippa As String
        If frmBilling.cbRoute.Text = "FOREIGN" Then
            route = "F"
        Else
            route = "D"
        End If
        If frmBilling.cbType.Text = "IN" Then
            type = "I"
        Else
            type = "O"
        End If

        If month = "01" And day <= 25 Then
            period = year + "001"
            reference = "RB010"
        ElseIf month = "01" And day > 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "02" And day <= 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "02" And day > 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "03" And day <= 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "03" And day > 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "04" And day <= 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "04" And day > 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "05" And day <= 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "05" And day > 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "06" And day <= 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "06" And day > 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "07" And day <= 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "07" And day > 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "08" And day <= 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "08" And day > 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "09" And day <= 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "09" And day > 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day <= 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day > 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day <= 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day > 25 Then
            period = year + "012"
            reference = "RB120"
        ElseIf month = "12" And day <= 31 Then
            period = year + "012"
            reference = "RB120"
        End If
        Dim no As String = CDbl(frmBilling.txtBillno.Text)
        ref = reference + no

        If frmBilling.txtVat.Text <> 0 Then
            indivat = "Y"
        Else
            indivat = "N"
        End If

        If frmBilling.txtLessppa.Text <> 0 Then
            indippa = "Y"
        Else
            indippa = "N"
        End If
        Try
            myCon.open()
            With frmBilling

                myCmd = New SqlCommand("INSERT INTO tbl_bill_trans (bt_bill_num,bt_account,bt_vessel,bt_voyage,bt_particulars,bt_route,bt_type,bt_date,bt_employee,bt_status,bt_acc_num,bt_total,bt_ppa,bt_net,bt_vat,bt_scipsi,bt_br,bt_per,bt_ref,bt_indivat,bt_indiprint,bt_indippa,bt_disc)VALUES('" & .txtBillno.Text & "', '" & .lblAccname.Text & "', '" & .txtVessel.Text & "','" & .txtVoyage.Text & "',  '" & .txtParticulars.Text & "', '" & route & "', '" & type & "', '" & time & "','" & .txtName.Text & "' , '" & status & "','" & .txtAccountno.Text & "', '" & Convert.ToDecimal(.txtTotal.Text) & "', '" & Convert.ToDecimal(.txtLessppa.Text) & "', '" & Convert.ToDecimal(.txtNet.Text) & "', '" & Convert.ToDecimal(.txtVat.Text) & "', '" & Convert.ToDecimal(.txtScipsi.Text) & "','RB001','" & period & "','" & ref & "' ,'" & indivat & "','N','" & indippa & "', '" & Convert.ToDecimal(.txtDiscount.Text) & "')", myCon)
                myCmd.ExecuteNonQuery()

                Dim mycmd1 = New SqlCommand("DELETE FROM tbl_bill_no WHERE b_num ='" & .txtBillno.Text & "'", myCon)
                mycmd1.ExecuteNonQuery()
            End With

        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
    End Sub

    Public Sub updateBill()
        Dim time As Date = frmBilling.lbldatetrans.Text
        Dim route As String
        Dim type As String

        Dim month As String = time.ToString("MM")
        Dim day As String = time.ToString("dd")
        Dim year As String = time.ToString("yyyy")
        Dim period As String
        Dim reference As String
        Dim ref As String
        Dim indivat As String
        Dim indippa As String
        If frmBilling.cbRoute.Text = "FOREIGN" Then
            route = "F"
        Else
            route = "D"
        End If
        If frmBilling.cbType.Text = "IN" Then
            type = "I"
        Else
            type = "O"
        End If

        If month = "01" And day <= 25 Then
            period = year + "001"
            reference = "RB010"
        ElseIf month = "01" And day > 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "02" And day <= 25 Then
            period = year + "002"
            reference = "RB020"
        ElseIf month = "02" And day > 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "03" And day <= 25 Then
            period = year + "003"
            reference = "RB030"
        ElseIf month = "03" And day > 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "04" And day <= 25 Then
            period = year + "004"
            reference = "RB040"
        ElseIf month = "04" And day > 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "05" And day <= 25 Then
            period = year + "005"
            reference = "RB050"
        ElseIf month = "05" And day > 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "06" And day <= 25 Then
            period = year + "006"
            reference = "RB060"
        ElseIf month = "06" And day > 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "07" And day <= 25 Then
            period = year + "007"
            reference = "RB070"
        ElseIf month = "07" And day > 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "08" And day <= 25 Then
            period = year + "008"
            reference = "RB080"
        ElseIf month = "08" And day > 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "09" And day <= 25 Then
            period = year + "009"
            reference = "RB090"
        ElseIf month = "09" And day > 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day <= 25 Then
            period = year + "010"
            reference = "RB100"
        ElseIf month = "10" And day > 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day <= 25 Then
            period = year + "011"
            reference = "RB110"
        ElseIf month = "11" And day > 25 Then
            period = year + "012"
            reference = "RB120"
        ElseIf month = "12" And day <= 31 Then
            period = year + "012"
            reference = "RB120"
        End If
        Dim no As String = CDbl(frmBilling.txtBillno.Text)
        ref = reference + no

        If frmBilling.txtVat.Text <> 0 Then
            indivat = "Y"
        Else
            indivat = "N"
        End If

        If frmBilling.txtLessppa.Text <> 0 Then
            indippa = "Y"
        Else
            indippa = "N"
        End If
        
        Try
            If frmBilling.txtperiod.Text <> period Then
                frmBilling.txtNotice.Text = "Date is out of the original period. Please try again."
            Else
                myCon.open()
                With frmBilling

                    myCmd = New SqlCommand(String.Format("UPDATE tbl_bill_trans SET bt_account='{0}',bt_vessel='{1}',bt_voyage='{2}',bt_particulars='{3}',bt_route = '{4}',bt_type='{5}',bt_date='{6}',bt_acc_num='{7}',bt_total='{8}',bt_ppa='{9}',bt_net='{10}',bt_vat='{11}',bt_scipsi='{12}',bt_per='{13}',bt_ref='{14}',bt_indivat='{15}',bt_indippa='{16}',bt_disc='{17}' WHERE bt_bill_num='{18}'", .lblAccname.Text, .txtVessel.Text, .txtVoyage.Text, .txtParticulars.Text, route, type, time, .txtAccountno.Text, Convert.ToDecimal(.txtTotal.Text), Convert.ToDecimal(.txtLessppa.Text), Convert.ToDecimal(.txtNet.Text), Convert.ToDecimal(.txtVat.Text), Convert.ToDecimal(.txtScipsi.Text), period, ref, indivat, indippa, Convert.ToDecimal(.txtDiscount.Text), .txtBillno.Text), myCon)
                    myCmd.ExecuteNonQuery()
                    frmBilling.txtNotice.Text = "Transaction Updated."
                End With
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myCmd.Dispose()
            myCon.close()
        End Try
    End Sub

    Public Sub billnostatus()

        Try

            myCon.open()
          
            Dim myCmd3 = New SqlCommand("SELECT * FROM tbl_bill_trans WHERE bt_bill_num = '" & frmBilling.txtBillno.Text & "'", myCon)
            Dim myReader1 As SqlDataReader = myCmd3.ExecuteReader()
            If myReader1.HasRows Then
                myReader1.Close()
                frmBilling.txtNotice.Text = "This bill # is already already use."
                frmBilling.btnAddItem.Enabled = False
                frmBilling.cbRoute.Enabled = False
                frmBilling.ppa.Enabled = False
                frmBilling.vat.Enabled = False
            Else
                myReader1.Close()


                Dim myCmd1 = New SqlCommand("SELECT * FROM tbl_bill_no WHERE b_num = '" & frmBilling.txtBillno.Text & "'", myCon)
                myReader = myCmd1.ExecuteReader()
                If myReader.HasRows Then
                    If (myReader.Read And myReader("b_user") = frmBilling.txtName.Text) Or myReader("b_user") = "" Then
                        frmBilling.txtTempno.Text = myReader("b_num")
                        frmBilling.btnAddItem.Enabled = True
                        frmBilling.cbRoute.Enabled = True
                        frmBilling.ppa.Enabled = True
                        frmBilling.vat.Enabled = True
                        myReader.Close()
                        myCmd = New SqlCommand("UPDATE tbl_bill_no SET b_user = '" & frmBilling.txtName.Text & "' WHERE b_num = '" & frmBilling.txtBillno.Text & "'", myCon)
                        myCmd.ExecuteNonQuery()



                        frmBilling.txtNotice.Text = ""
                    Else
                        frmBilling.txtNotice.Text = "This Bill # is on process by " + myReader("b_user")
                        frmBilling.btnAddItem.Enabled = False
                        frmBilling.cbRoute.Enabled = False
                        frmBilling.ppa.Enabled = False
                        frmBilling.vat.Enabled = False

                    End If



                Else
                    frmBilling.btnAddItem.Enabled = False
                    frmBilling.cbRoute.Enabled = False
                    frmBilling.ppa.Enabled = False
                    frmBilling.vat.Enabled = False
                    frmBilling.txtNotice.Text = "Bill number doesn’t Exist."
                End If
                myReader.Close()

                If frmBilling.txtBillno.Text <> frmBilling.txtTempno.Text Then
                    Dim mycmd2 = New SqlCommand("UPDATE tbl_bill_no SET b_user='' WHERE b_num = '" & frmBilling.txtTempno.Text & "'", myCon)
                    mycmd2.ExecuteNonQuery()
                    frmBilling.txtTempno.Text = ""

                End If
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myReader.Close()
            myCon.Close()
        End Try

       

    End Sub

    Public Sub billnostatus1()

        'myCon.open()
        'myCmd = New SqlCommand("UPDATE tbl_bill_no SET b_status = 'Y' WHERE b_num = '" & frmBilling.txtBillno.Text & "'", myCon)
        'myCmd.ExecuteNonQuery()
        'myCon.close()

    End Sub

    Public Sub billnostatus2()
        'myCon.open()
        'If frmBilling.txtBillno.Text <> frmBilling.txtTempno.Text Then
        '    Dim mycmd2 = New SqlCommand("UPDATE tbl_bill_no SET b_status = 'N' WHERE b_num = '" & frmBilling.txtTempno.Text & "'", myCon)
        '    mycmd2.ExecuteNonQuery()
        '    frmBilling.txtTempno.Text = ""
        '    mycmd2.Dispose()
        'End If

        'myCon.close()

    End Sub

    Public Sub paidsearch()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)
        myCmd = New SqlCommand(String.Format("SELECT or_id,or_num,or_acc_num,or_accname,or_date,or_bill_amount,or_status FROM tbl_or_trans WHERE or_acc_num = '{0}' OR or_accname LIKE '%{0}%' AND or_status = 'FALSE' ORDER BY or_id DESC ", searchPaid.txtSearch.Text), myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        myAdapter.Fill(dt)

        With searchPaid
            .dgPaid.DataSource = dt.DefaultView
        End With
        myAdapter.Dispose()
        myCon.close()
    End Sub
End Module

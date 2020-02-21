Imports System.Data
Imports System.Data.SqlClient
Module ORModule
    Public Sub vORno()
        'If frmOR.txtOrno.Text = "" Then
        '    frmOR.txtOrno.Focus()
        '    frmOR.txtNotice.Text = "Please Enter OR number!"
        'Else

        '    Try
        '        myCon.open()
        '        myCmd = New SqlCommand("SELECT * FROM tbl_or_no WHERE or_num = '" & frmOR.txtOrno.Text & "'", myCon)
        '        myReader = myCmd.ExecuteReader()

        '        If myReader.HasRows Then
        '            frmOR.txtNotice.Text = ""

        '        Else
        '            frmOR.txtOrno.Text = ""
        '            frmOR.txtNotice.Text = "Invalid OR number! Please Try Again."
        '            frmOR.txtOrno.Focus()

        '        End If

        '    Catch ex As Exception
        '        frmOR.txtNotice.Text = "Invalid OR number! Please Try Again."
        '    Finally
        '        myCmd.Dispose()
        '        myCon.close()
        '    End Try
        'End If



        Try


            myCon.open()

            Dim myCmd3 = New SqlCommand(String.Format("SELECT * FROM tbl_or_trans WHERE or_num = '{0}'", frmor.txtOrno.Text), myCon)
            Dim myReader1 As SqlDataReader = myCmd3.ExecuteReader()
            If myReader1.HasRows Then
                myReader1.Close()
                frmor.txtNotice.Text = "This OR # is already already use."
                frmor.btnNewOR.Enabled = False
            Else
                myReader1.Close()

                Dim myCmd1 = New SqlCommand(String.Format("SELECT * FROM tbl_or_no WHERE or_num = '{0}'", frmor.txtOrno.Text), myCon)
                myReader = myCmd1.ExecuteReader()
                If myReader.HasRows Then
                    If (myReader.Read And myReader("or_user") = frmBilling.txtnametemp.Text) Or myReader("or_user") = "" Then
                        frmor.txtTempno.Text = myReader("or_num")
                        frmor.btnAddOr.Enabled = True
                        frmor.btnpartial.Enabled = True
                        frmor.btnDeposit.Enabled = True
                        myReader.Close()
                        myCmd = New SqlCommand(String.Format("UPDATE tbl_or_no SET or_user = '{0}' WHERE or_num = '{1}'", frmBilling.txtnametemp.Text, frmor.txtOrno.Text), myCon)
                        myCmd.ExecuteNonQuery()

                        frmor.txtNotice.Text = ""
                    Else
                        frmor.txtNotice.Text = "This OR # is on process by " + myReader("or_user")
                        frmor.btnAddOr.Enabled = False
                        frmor.btnpartial.Enabled = False
                        frmor.btnDeposit.Enabled = False

                    End If



                Else
                    frmor.btnAddOr.Enabled = False
                    frmor.btnpartial.Enabled = False
                    frmor.btnDeposit.Enabled = False
                    frmor.txtNotice.Text = "OR number doesn't Exist."
                End If
                myReader.Close()

                If frmor.txtOrno.Text <> frmor.txtTempno.Text Then
                    Dim mycmd2 = New SqlCommand(String.Format("UPDATE tbl_or_no SET or_user='' WHERE or_num = '{0}'", frmor.txtTempno.Text), myCon)
                    mycmd2.ExecuteNonQuery()
                    frmor.txtTempno.Text = ""

                End If
            End If
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
            myReader.Close()
            myCon.Close()
        End Try


    End Sub

    Public Sub vORAccno()

        If frmor.txtAccountno.Text = "     -" Then
            frmor.txtAccountno.Focus()
            frmor.txtNotice.Text = "Please Enter Account number!"
            frmor.lblAccname.Text = ""
            frmor.lblAddress.Text = ""
            frmor.lblTin.Text = ""
            frmor.lblBstyle.Text = ""
        Else


            Try
                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_account WHERE ac_code = '" & frmor.txtAccountno.Text & "'", myCon)
                myReader = myCmd.ExecuteReader()

                If myReader.HasRows Then
                    If myReader.Read Then
                        If (myReader("ac_name") = "WALK-IN" And frmor.lblAccname.Text = "") Then
                            frmor.labelstatus.Text = 1
                            frmWalkin1.ShowDialog()
                        Else
                            If myReader("ac_name") = "WALK-IN" Then
                            Else
                                frmor.labelstatus.Text = 0
                                frmor.lblAccname.Text = myReader("ac_name")
                                frmor.lblAddress.Text = myReader("ac_address")
                                frmor.lblTin.Text = myReader("ac_tin")
                                frmor.lblBstyle.Text = myReader("ac_busi")
                            End If
                        End If

                    End If
                Else
                    frmor.lblAccname.Text = ""
                    frmor.txtAccountno.Text = ""
                    frmor.lblAddress.Text = ""
                    frmor.lblTin.Text = ""
                    frmor.lblBstyle.Text = ""
                    frmor.txtAccountno.Focus()
                    frmor.txtNotice.Text = "Invalid Account number! Please Try Again."


                End If

            Catch ex As Exception
                frmor.lblAccname.Text = ""
                frmor.txtAccountno.Text = ""
                frmor.lblAddress.Text = ""
                frmor.lblTin.Text = ""
                frmor.lblBstyle.Text = ""
                frmor.txtAccountno.Focus()
                frmor.txtNotice.Text = "Invalid Account number! Please Try Again."
            Finally
                myCmd.Dispose()
                myCon.close()
            End Try
        End If

    End Sub
    Public Sub searchClientNewDetails(ByVal ClientCode As String)
        myCon.open()
        myCmd = New SqlCommand("SELECT * FROM tbl_account WHERE ac_code = '" & ClientCode & "'", myCon)
        myReader = myCmd.ExecuteReader()

        If myReader.HasRows Then
            If myReader.Read Then
                frmor.lblAddress.Text = myReader("ac_address")
                frmor.lblTin.Text = myReader("ac_tin")
                frmor.lblBstyle.Text = myReader("ac_busi")
            End If
        End If
        myCmd.Dispose()
        myCon.close()
    End Sub

    Public Sub partialbill()
        On Error Resume Next
        Dim change As Integer = frmPartial.txtbillno.Text
        frmPartial.txtbillno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
        myCon.open()
        Dim myCmd1 = New SqlCommand("SELECT * FROM tbl_bill_trans WHERE bt_bill_num ='" & frmPartial.txtbillno.Text & "'", myCon)
        Dim myReader1 = myCmd1.ExecuteReader
        If myReader1.HasRows = False Then
            frmor.txtNotice.Text = "Bill invoice not found."
            frmPartial.txtbillno.Focus()
            myReader1.dispose()
            myCmd1.dispose()
            myCon.close()
        ElseIf frmPartial.txtbillno.Text = "" Then
            frmPartial.txtbillno.Focus()
            frmor.txtNotice.Text = "Please Enter Bill number!"
        Else
            '-------------------------VALIDATIONS------------------------------

            Dim exists As Boolean = False
            For Each itm As DataGridViewRow In frmor.dgOrItems.Rows
                If itm.Cells(0).Value = frmPartial.txtbillno.Text Then
                    exists = True
                End If
            Next
            '-------------------------VALIDATIONS------------------------------


            myReader1.dispose()

            Dim myCmd2 = New SqlCommand("SELECT * FROM tbl_orbill_trans WHERE ot_bill_no = '" & frmPartial.txtbillno.Text & "' ORDER BY ot_id DESC", myCon)
            Dim myReader2 As SqlDataReader = myCmd2.ExecuteReader()

            If exists = True Then
                frmor.txtNotice.Text = "This Bill number is on Process."
                frmPartial.txtbillno.Focus()
            ElseIf myReader2.HasRows = True Then
                If myReader2.Read Then
                    If myReader2("ot_indipartial") = "F" And myReader2("ot_balance") = ".00" Then
                        frmor.txtNotice.Text = "This bill invoice is fully paid."
                        frmPartial.txtbillno.Focus()
                    ElseIf myReader2("ot_indipartial") = "P" And myReader2("ot_balance") <> ".00" Then
                        frmPartial.amt.Text = myReader2("ot_balance")
                        frmor.txtNotice.Text = ""
                    End If
                End If
                myCmd2.dispose()
                myReader2.Dispose()
                myCon.close()
            ElseIf myReader2.HasRows = False Then
                myCmd2.dispose()
                myReader2.Dispose()
                Dim myCmd3 = New SqlCommand("SELECT * FROM tbl_bill_trans WHERE bt_bill_num ='" & frmPartial.txtbillno.Text & "'", myCon)
                Dim myReader3 As SqlDataReader = myCmd3.ExecuteReader
                If myReader3.HasRows Then
                    If myReader3.Read Then
                        frmPartial.amt.Text = myReader3("bt_scipsi")
                    End If
                End If
                myCmd3.dispose()
                myReader3.Dispose()
                myCon.close()
                '-------------------------VALIDATIONS------------------------------

            ElseIf frmor.dgOrItems.Rows.Count > 11 Then
                frmor.btnAddOr.Enabled = False
                frmor.btnpartial.Enabled = False
                frmor.txtNotice.Text = "Your OR transaction has reach to its maximum items."
            Else

                frmor.txtNotice.Text = "Bill invoice not found."

                'orBillNo()
                'viewORItems()







            End If
        End If
        myCon.close()
    End Sub
    Public Sub savePartial()
        Try
            myCon.open()
            myCmd = New SqlCommand("INSERT INTO tbl_orbill_trans (ot_or_no,ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_net,ot_partial,ot_balance,ot_indipartial,ot_disc) VALUES ('" & frmor.txtOrno.Text & "','" & frmPartial.txtbillno.Text & "','" & Convert.ToDecimal(frmPartial.vat.Text) & "','" & Convert.ToDecimal(frmPartial.amt.Text) & "','" & Convert.ToDecimal(frmPartial.htax.Text) & "','" & Convert.ToDecimal(frmPartial.txtNet.Text) & "','" & Convert.ToDecimal(frmPartial.txtNet.Text) & "','" & Convert.ToDecimal(frmPartial.txtBal.Text) & "','" & frmPartial.txtindiPartial.Text & "',0)", myCon)
            myCmd.ExecuteNonQuery()
            frmPartial.Dispose()

            myCon.close()
            viewORItems()
            frmor.txtNotice.Text = ""
        Catch ex As Exception
            frmor.txtNotice.Text = "Please enter right amount."
        End Try

    End Sub

    Public Sub bank()
        myCon.open()
        myCmd = New SqlCommand("SELECT * FROM tbl_bank", myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        Dim table As New DataTable
        myAdapter.Fill(table)
        frmor.txtbank.DataSource = New BindingSource(table, Nothing)
        frmor.txtbank.DisplayMember = "b_code"
        frmor.txtbank.ValueMember = "b_code"
        myAdapter.Dispose()
        myCon.close()
    End Sub


End Module

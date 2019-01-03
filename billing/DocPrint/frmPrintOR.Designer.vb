<Global.Microsoft.VisualBasic.CompilerServices.DesignerGenerated()> _
Partial Class frmPrintOR
    Inherits System.Windows.Forms.Form

    'Form overrides dispose to clean up the component list.
    <System.Diagnostics.DebuggerNonUserCode()> _
    Protected Overrides Sub Dispose(ByVal disposing As Boolean)
        Try
            If disposing AndAlso components IsNot Nothing Then
                components.Dispose()
            End If
        Finally
            MyBase.Dispose(disposing)
        End Try
    End Sub

    'Required by the Windows Form Designer
    Private components As System.ComponentModel.IContainer

    'NOTE: The following procedure is required by the Windows Form Designer
    'It can be modified using the Windows Form Designer.  
    'Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> _
    Private Sub InitializeComponent()
        Dim DataGridViewCellStyle6 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle7 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle8 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle9 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Dim DataGridViewCellStyle10 As System.Windows.Forms.DataGridViewCellStyle = New System.Windows.Forms.DataGridViewCellStyle()
        Me.Panel1 = New System.Windows.Forms.Panel()
        Me.lblBstyle = New System.Windows.Forms.Label()
        Me.lblTin = New System.Windows.Forms.Label()
        Me.lblAddress = New System.Windows.Forms.Label()
        Me.netbox = New System.Windows.Forms.ListBox()
        Me.lblDiscount = New System.Windows.Forms.TextBox()
        Me.billbox = New System.Windows.Forms.ListBox()
        Me.txtpartial = New System.Windows.Forms.TextBox()
        Me.lblval = New System.Windows.Forms.TextBox()
        Me.lblTotal = New System.Windows.Forms.TextBox()
        Me.lbl = New System.Windows.Forms.Label()
        Me.PictureBox1 = New System.Windows.Forms.PictureBox()
        Me.dgOrItem = New System.Windows.Forms.DataGridView()
        Me.lblChange = New System.Windows.Forms.Label()
        Me.Label11 = New System.Windows.Forms.Label()
        Me.lblCash = New System.Windows.Forms.Label()
        Me.Label9 = New System.Windows.Forms.Label()
        Me.lblTax = New System.Windows.Forms.Label()
        Me.lblAmt = New System.Windows.Forms.Label()
        Me.lblVat = New System.Windows.Forms.Label()
        Me.Label4 = New System.Windows.Forms.Label()
        Me.Label3 = New System.Windows.Forms.Label()
        Me.Label2 = New System.Windows.Forms.Label()
        Me.Label1 = New System.Windows.Forms.Label()
        Me.txtCheck = New System.Windows.Forms.Label()
        Me.txtEmp = New System.Windows.Forms.Label()
        Me.lblclient = New System.Windows.Forms.Label()
        Me.lbldate = New System.Windows.Forms.Label()
        Me.txtword = New System.Windows.Forms.TextBox()
        Me.PrintDocument1 = New System.Drawing.Printing.PrintDocument()
        Me.Panel1.SuspendLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).BeginInit()
        CType(Me.dgOrItem, System.ComponentModel.ISupportInitialize).BeginInit()
        Me.SuspendLayout()
        '
        'Panel1
        '
        Me.Panel1.BackColor = System.Drawing.Color.White
        Me.Panel1.Controls.Add(Me.lblBstyle)
        Me.Panel1.Controls.Add(Me.lblTin)
        Me.Panel1.Controls.Add(Me.lblAddress)
        Me.Panel1.Controls.Add(Me.netbox)
        Me.Panel1.Controls.Add(Me.lblDiscount)
        Me.Panel1.Controls.Add(Me.billbox)
        Me.Panel1.Controls.Add(Me.txtpartial)
        Me.Panel1.Controls.Add(Me.lblval)
        Me.Panel1.Controls.Add(Me.lblTotal)
        Me.Panel1.Controls.Add(Me.lbl)
        Me.Panel1.Controls.Add(Me.PictureBox1)
        Me.Panel1.Controls.Add(Me.dgOrItem)
        Me.Panel1.Controls.Add(Me.lblChange)
        Me.Panel1.Controls.Add(Me.Label11)
        Me.Panel1.Controls.Add(Me.lblCash)
        Me.Panel1.Controls.Add(Me.Label9)
        Me.Panel1.Controls.Add(Me.lblTax)
        Me.Panel1.Controls.Add(Me.lblAmt)
        Me.Panel1.Controls.Add(Me.lblVat)
        Me.Panel1.Controls.Add(Me.Label4)
        Me.Panel1.Controls.Add(Me.Label3)
        Me.Panel1.Controls.Add(Me.Label2)
        Me.Panel1.Controls.Add(Me.Label1)
        Me.Panel1.Controls.Add(Me.txtCheck)
        Me.Panel1.Controls.Add(Me.txtEmp)
        Me.Panel1.Controls.Add(Me.lblclient)
        Me.Panel1.Controls.Add(Me.lbldate)
        Me.Panel1.Controls.Add(Me.txtword)
        Me.Panel1.ForeColor = System.Drawing.SystemColors.WindowText
        Me.Panel1.Location = New System.Drawing.Point(1, 1)
        Me.Panel1.Margin = New System.Windows.Forms.Padding(4)
        Me.Panel1.Name = "Panel1"
        Me.Panel1.Size = New System.Drawing.Size(1152, 496)
        Me.Panel1.TabIndex = 5
        '
        'lblBstyle
        '
        Me.lblBstyle.AutoSize = True
        Me.lblBstyle.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblBstyle.Location = New System.Drawing.Point(606, 219)
        Me.lblBstyle.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblBstyle.Name = "lblBstyle"
        Me.lblBstyle.Size = New System.Drawing.Size(46, 17)
        Me.lblBstyle.TabIndex = 63
        Me.lblBstyle.Text = "Bstyle"
        '
        'lblTin
        '
        Me.lblTin.AutoSize = True
        Me.lblTin.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblTin.Location = New System.Drawing.Point(875, 219)
        Me.lblTin.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblTin.Name = "lblTin"
        Me.lblTin.Size = New System.Drawing.Size(28, 17)
        Me.lblTin.TabIndex = 62
        Me.lblTin.Text = "Tin"
        '
        'lblAddress
        '
        Me.lblAddress.AutoSize = True
        Me.lblAddress.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblAddress.Location = New System.Drawing.Point(551, 193)
        Me.lblAddress.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblAddress.Name = "lblAddress"
        Me.lblAddress.Size = New System.Drawing.Size(60, 17)
        Me.lblAddress.TabIndex = 61
        Me.lblAddress.Text = "Address"
        '
        'netbox
        '
        Me.netbox.FormattingEnabled = True
        Me.netbox.ItemHeight = 16
        Me.netbox.Location = New System.Drawing.Point(300, 41)
        Me.netbox.Margin = New System.Windows.Forms.Padding(4)
        Me.netbox.Name = "netbox"
        Me.netbox.Size = New System.Drawing.Size(159, 116)
        Me.netbox.TabIndex = 60
        '
        'lblDiscount
        '
        Me.lblDiscount.BackColor = System.Drawing.Color.White
        Me.lblDiscount.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.lblDiscount.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblDiscount.Location = New System.Drawing.Point(323, 281)
        Me.lblDiscount.Margin = New System.Windows.Forms.Padding(4)
        Me.lblDiscount.Name = "lblDiscount"
        Me.lblDiscount.Size = New System.Drawing.Size(228, 16)
        Me.lblDiscount.TabIndex = 47
        Me.lblDiscount.Text = "aaaaa"
        '
        'billbox
        '
        Me.billbox.FormattingEnabled = True
        Me.billbox.ItemHeight = 16
        Me.billbox.Location = New System.Drawing.Point(132, 41)
        Me.billbox.Margin = New System.Windows.Forms.Padding(4)
        Me.billbox.Name = "billbox"
        Me.billbox.Size = New System.Drawing.Size(159, 116)
        Me.billbox.TabIndex = 59
        '
        'txtpartial
        '
        Me.txtpartial.BackColor = System.Drawing.Color.White
        Me.txtpartial.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtpartial.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtpartial.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtpartial.Location = New System.Drawing.Point(592, 319)
        Me.txtpartial.Margin = New System.Windows.Forms.Padding(4, 4, 0, 0)
        Me.txtpartial.Multiline = True
        Me.txtpartial.Name = "txtpartial"
        Me.txtpartial.ReadOnly = True
        Me.txtpartial.Size = New System.Drawing.Size(497, 36)
        Me.txtpartial.TabIndex = 46
        Me.txtpartial.TabStop = False
        '
        'lblval
        '
        Me.lblval.BackColor = System.Drawing.Color.White
        Me.lblval.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.lblval.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblval.Location = New System.Drawing.Point(896, 300)
        Me.lblval.Margin = New System.Windows.Forms.Padding(4)
        Me.lblval.Name = "lblval"
        Me.lblval.ReadOnly = True
        Me.lblval.Size = New System.Drawing.Size(228, 16)
        Me.lblval.TabIndex = 2
        Me.lblval.TabStop = False
        Me.lblval.Text = "value"
        '
        'lblTotal
        '
        Me.lblTotal.BackColor = System.Drawing.Color.White
        Me.lblTotal.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.lblTotal.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblTotal.Location = New System.Drawing.Point(323, 310)
        Me.lblTotal.Margin = New System.Windows.Forms.Padding(4)
        Me.lblTotal.Name = "lblTotal"
        Me.lblTotal.Size = New System.Drawing.Size(228, 16)
        Me.lblTotal.TabIndex = 45
        Me.lblTotal.Text = "aaaaa"
        '
        'lbl
        '
        Me.lbl.AutoSize = True
        Me.lbl.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbl.Location = New System.Drawing.Point(320, 266)
        Me.lbl.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbl.Name = "lbl"
        Me.lbl.Size = New System.Drawing.Size(83, 17)
        Me.lbl.TabIndex = 43
        Me.lbl.Text = "DISCOUNT:"
        '
        'PictureBox1
        '
        Me.PictureBox1.BackgroundImage = Global.billing.My.Resources.Resources.checkmark_26
        Me.PictureBox1.BackgroundImageLayout = System.Windows.Forms.ImageLayout.Stretch
        Me.PictureBox1.Location = New System.Drawing.Point(192, 390)
        Me.PictureBox1.Margin = New System.Windows.Forms.Padding(4)
        Me.PictureBox1.Name = "PictureBox1"
        Me.PictureBox1.Size = New System.Drawing.Size(20, 14)
        Me.PictureBox1.TabIndex = 42
        Me.PictureBox1.TabStop = False
        Me.PictureBox1.Visible = False
        '
        'dgOrItem
        '
        Me.dgOrItem.AllowUserToAddRows = False
        Me.dgOrItem.AllowUserToDeleteRows = False
        DataGridViewCellStyle6.BackColor = System.Drawing.Color.White
        Me.dgOrItem.AlternatingRowsDefaultCellStyle = DataGridViewCellStyle6
        Me.dgOrItem.BackgroundColor = System.Drawing.Color.White
        Me.dgOrItem.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.dgOrItem.ColumnHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle7.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleCenter
        DataGridViewCellStyle7.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle7.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.249999!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle7.ForeColor = System.Drawing.SystemColors.ControlText
        DataGridViewCellStyle7.SelectionBackColor = System.Drawing.Color.LightSteelBlue
        DataGridViewCellStyle7.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle7.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.dgOrItem.ColumnHeadersDefaultCellStyle = DataGridViewCellStyle7
        Me.dgOrItem.ColumnHeadersHeight = 24
        Me.dgOrItem.Cursor = System.Windows.Forms.Cursors.Hand
        DataGridViewCellStyle8.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle8.BackColor = System.Drawing.SystemColors.Window
        DataGridViewCellStyle8.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle8.ForeColor = System.Drawing.SystemColors.WindowText
        DataGridViewCellStyle8.SelectionBackColor = System.Drawing.SystemColors.Highlight
        DataGridViewCellStyle8.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle8.WrapMode = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgOrItem.DefaultCellStyle = DataGridViewCellStyle8
        Me.dgOrItem.EnableHeadersVisualStyles = False
        Me.dgOrItem.GridColor = System.Drawing.Color.White
        Me.dgOrItem.Location = New System.Drawing.Point(167, 74)
        Me.dgOrItem.Margin = New System.Windows.Forms.Padding(4)
        Me.dgOrItem.MultiSelect = False
        Me.dgOrItem.Name = "dgOrItem"
        Me.dgOrItem.ReadOnly = True
        Me.dgOrItem.RowHeadersBorderStyle = System.Windows.Forms.DataGridViewHeaderBorderStyle.[Single]
        DataGridViewCellStyle9.Alignment = System.Windows.Forms.DataGridViewContentAlignment.MiddleLeft
        DataGridViewCellStyle9.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle9.Font = New System.Drawing.Font("Microsoft Sans Serif", 5.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle9.ForeColor = System.Drawing.Color.White
        DataGridViewCellStyle9.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle9.SelectionForeColor = System.Drawing.SystemColors.HighlightText
        DataGridViewCellStyle9.WrapMode = System.Windows.Forms.DataGridViewTriState.[True]
        Me.dgOrItem.RowHeadersDefaultCellStyle = DataGridViewCellStyle9
        Me.dgOrItem.RowHeadersVisible = False
        Me.dgOrItem.RowHeadersWidth = 25
        Me.dgOrItem.RowHeadersWidthSizeMode = System.Windows.Forms.DataGridViewRowHeadersWidthSizeMode.DisableResizing
        DataGridViewCellStyle10.BackColor = System.Drawing.Color.White
        DataGridViewCellStyle10.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        DataGridViewCellStyle10.ForeColor = System.Drawing.Color.Black
        DataGridViewCellStyle10.SelectionBackColor = System.Drawing.Color.White
        DataGridViewCellStyle10.SelectionForeColor = System.Drawing.Color.Black
        Me.dgOrItem.RowsDefaultCellStyle = DataGridViewCellStyle10
        Me.dgOrItem.RowTemplate.Height = 18
        Me.dgOrItem.RowTemplate.Resizable = System.Windows.Forms.DataGridViewTriState.[False]
        Me.dgOrItem.ScrollBars = System.Windows.Forms.ScrollBars.None
        Me.dgOrItem.SelectionMode = System.Windows.Forms.DataGridViewSelectionMode.CellSelect
        Me.dgOrItem.Size = New System.Drawing.Size(303, 162)
        Me.dgOrItem.TabIndex = 41
        '
        'lblChange
        '
        Me.lblChange.AutoSize = True
        Me.lblChange.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblChange.Location = New System.Drawing.Point(320, 369)
        Me.lblChange.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblChange.Name = "lblChange"
        Me.lblChange.Size = New System.Drawing.Size(40, 17)
        Me.lblChange.TabIndex = 17
        Me.lblChange.Text = "aaaa"
        '
        'Label11
        '
        Me.Label11.AutoSize = True
        Me.Label11.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label11.Location = New System.Drawing.Point(320, 354)
        Me.Label11.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label11.Name = "Label11"
        Me.Label11.Size = New System.Drawing.Size(70, 17)
        Me.Label11.TabIndex = 16
        Me.Label11.Text = "CHANGE:"
        '
        'lblCash
        '
        Me.lblCash.AutoSize = True
        Me.lblCash.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblCash.Location = New System.Drawing.Point(320, 340)
        Me.lblCash.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblCash.Name = "lblCash"
        Me.lblCash.Size = New System.Drawing.Size(40, 17)
        Me.lblCash.TabIndex = 15
        Me.lblCash.Text = "aaaa"
        '
        'Label9
        '
        Me.Label9.AutoSize = True
        Me.Label9.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label9.Location = New System.Drawing.Point(320, 325)
        Me.Label9.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label9.Name = "Label9"
        Me.Label9.Size = New System.Drawing.Size(49, 17)
        Me.Label9.TabIndex = 14
        Me.Label9.Text = "CASH:"
        '
        'lblTax
        '
        Me.lblTax.AutoSize = True
        Me.lblTax.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblTax.Location = New System.Drawing.Point(320, 251)
        Me.lblTax.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblTax.Name = "lblTax"
        Me.lblTax.Size = New System.Drawing.Size(40, 17)
        Me.lblTax.TabIndex = 12
        Me.lblTax.Text = "aaaa"
        '
        'lblAmt
        '
        Me.lblAmt.AutoSize = True
        Me.lblAmt.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblAmt.Location = New System.Drawing.Point(128, 281)
        Me.lblAmt.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblAmt.Name = "lblAmt"
        Me.lblAmt.Size = New System.Drawing.Size(40, 17)
        Me.lblAmt.TabIndex = 11
        Me.lblAmt.Text = "aaaa"
        '
        'lblVat
        '
        Me.lblVat.AutoSize = True
        Me.lblVat.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblVat.Location = New System.Drawing.Point(128, 251)
        Me.lblVat.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblVat.Name = "lblVat"
        Me.lblVat.Size = New System.Drawing.Size(40, 17)
        Me.lblVat.TabIndex = 10
        Me.lblVat.Text = "aaaa"
        '
        'Label4
        '
        Me.Label4.AutoSize = True
        Me.Label4.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label4.Location = New System.Drawing.Point(320, 295)
        Me.Label4.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label4.Name = "Label4"
        Me.Label4.Size = New System.Drawing.Size(91, 17)
        Me.Label4.TabIndex = 9
        Me.Label4.Text = "TOTAL DUE:"
        '
        'Label3
        '
        Me.Label3.AutoSize = True
        Me.Label3.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label3.Location = New System.Drawing.Point(320, 236)
        Me.Label3.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label3.Name = "Label3"
        Me.Label3.Size = New System.Drawing.Size(62, 17)
        Me.Label3.TabIndex = 8
        Me.Label3.Text = "WHTAX:"
        '
        'Label2
        '
        Me.Label2.AutoSize = True
        Me.Label2.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label2.Location = New System.Drawing.Point(128, 266)
        Me.Label2.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label2.Name = "Label2"
        Me.Label2.Size = New System.Drawing.Size(77, 17)
        Me.Label2.TabIndex = 7
        Me.Label2.Text = "BILL AMT.:"
        '
        'Label1
        '
        Me.Label1.AutoSize = True
        Me.Label1.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.Label1.Location = New System.Drawing.Point(128, 236)
        Me.Label1.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.Label1.Name = "Label1"
        Me.Label1.Size = New System.Drawing.Size(71, 17)
        Me.Label1.TabIndex = 6
        Me.Label1.Text = "BILL VAT:"
        '
        'txtCheck
        '
        Me.txtCheck.AutoSize = True
        Me.txtCheck.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtCheck.Location = New System.Drawing.Point(320, 390)
        Me.txtCheck.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.txtCheck.Name = "txtCheck"
        Me.txtCheck.Size = New System.Drawing.Size(0, 17)
        Me.txtCheck.TabIndex = 4
        '
        'txtEmp
        '
        Me.txtEmp.AutoSize = True
        Me.txtEmp.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Bold, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtEmp.Location = New System.Drawing.Point(729, 354)
        Me.txtEmp.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.txtEmp.Name = "txtEmp"
        Me.txtEmp.Size = New System.Drawing.Size(38, 17)
        Me.txtEmp.TabIndex = 3
        Me.txtEmp.Text = "emp"
        '
        'lblclient
        '
        Me.lblclient.AutoSize = True
        Me.lblclient.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lblclient.Location = New System.Drawing.Point(551, 165)
        Me.lblclient.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lblclient.Name = "lblclient"
        Me.lblclient.Size = New System.Drawing.Size(41, 17)
        Me.lblclient.TabIndex = 1
        Me.lblclient.Text = "client"
        '
        'lbldate
        '
        Me.lbldate.AutoSize = True
        Me.lbldate.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.lbldate.Location = New System.Drawing.Point(807, 142)
        Me.lbldate.Margin = New System.Windows.Forms.Padding(4, 0, 4, 0)
        Me.lbldate.Name = "lbldate"
        Me.lbldate.Size = New System.Drawing.Size(36, 17)
        Me.lbldate.TabIndex = 0
        Me.lbldate.Text = "date"
        '
        'txtword
        '
        Me.txtword.BackColor = System.Drawing.Color.White
        Me.txtword.BorderStyle = System.Windows.Forms.BorderStyle.None
        Me.txtword.CharacterCasing = System.Windows.Forms.CharacterCasing.Upper
        Me.txtword.Font = New System.Drawing.Font("Microsoft Sans Serif", 8.25!, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, CType(0, Byte))
        Me.txtword.Location = New System.Drawing.Point(564, 260)
        Me.txtword.Margin = New System.Windows.Forms.Padding(4, 4, 0, 0)
        Me.txtword.Multiline = True
        Me.txtword.Name = "txtword"
        Me.txtword.ReadOnly = True
        Me.txtword.Size = New System.Drawing.Size(525, 36)
        Me.txtword.TabIndex = 20
        Me.txtword.TabStop = False
        '
        'PrintDocument1
        '
        '
        'frmPrintOR
        '
        Me.AutoScaleDimensions = New System.Drawing.SizeF(8.0!, 16.0!)
        Me.AutoScaleMode = System.Windows.Forms.AutoScaleMode.Font
        Me.ClientSize = New System.Drawing.Size(1155, 498)
        Me.Controls.Add(Me.Panel1)
        Me.Margin = New System.Windows.Forms.Padding(4)
        Me.Name = "frmPrintOR"
        Me.Text = "frmPrintOR"
        Me.Panel1.ResumeLayout(False)
        Me.Panel1.PerformLayout()
        CType(Me.PictureBox1, System.ComponentModel.ISupportInitialize).EndInit()
        CType(Me.dgOrItem, System.ComponentModel.ISupportInitialize).EndInit()
        Me.ResumeLayout(False)

    End Sub
    Friend WithEvents Panel1 As System.Windows.Forms.Panel
    Friend WithEvents txtCheck As System.Windows.Forms.Label
    Friend WithEvents txtEmp As System.Windows.Forms.Label
    Friend WithEvents txtword As System.Windows.Forms.TextBox
    Friend WithEvents lblclient As System.Windows.Forms.Label
    Friend WithEvents lbldate As System.Windows.Forms.Label
    Friend WithEvents lblChange As System.Windows.Forms.Label
    Friend WithEvents Label11 As System.Windows.Forms.Label
    Friend WithEvents lblCash As System.Windows.Forms.Label
    Friend WithEvents Label9 As System.Windows.Forms.Label
    Friend WithEvents lblTax As System.Windows.Forms.Label
    Friend WithEvents lblAmt As System.Windows.Forms.Label
    Friend WithEvents lblVat As System.Windows.Forms.Label
    Friend WithEvents Label4 As System.Windows.Forms.Label
    Friend WithEvents Label3 As System.Windows.Forms.Label
    Friend WithEvents Label2 As System.Windows.Forms.Label
    Friend WithEvents Label1 As System.Windows.Forms.Label
    Friend WithEvents dgOrItem As System.Windows.Forms.DataGridView
    Friend WithEvents PrintDocument1 As System.Drawing.Printing.PrintDocument
    Friend WithEvents PictureBox1 As System.Windows.Forms.PictureBox
    Friend WithEvents lbl As System.Windows.Forms.Label
    Friend WithEvents lblTotal As System.Windows.Forms.TextBox
    Friend WithEvents lblval As System.Windows.Forms.TextBox
    Friend WithEvents txtpartial As System.Windows.Forms.TextBox
    Friend WithEvents lblDiscount As System.Windows.Forms.TextBox
    Friend WithEvents netbox As System.Windows.Forms.ListBox
    Friend WithEvents billbox As System.Windows.Forms.ListBox
    Friend WithEvents lblBstyle As Label
    Friend WithEvents lblTin As Label
    Friend WithEvents lblAddress As Label
End Class
